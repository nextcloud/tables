<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="row main-context-view">
		<div v-if="loading" class="icon-loading loading-placeholder" />

		<div v-else-if="activeContext">
			<div class="content context">
				<div class="row first-row">
					<h1 class="context__title" data-cy="context-title">
						<NcIconSvgWrapper :svg="icon" :size="32" style="display: inline-block;" />&nbsp; {{
							activeContext.name }}
					</h1>
				</div>
				<div class="row space-L context__description">
					{{ activeContext.description }}
				</div>
				<ContextPageBar :context="activeContext"
					:selected-page-id="selectedPageId"
					:panel-id="pagePanelId"
					:can-manage="canManage"
					@select="openPage"
					@add-page="openPageForm(null)" />
			</div>

			<div :id="pagePanelId"
				role="tabpanel"
				:aria-labelledby="selectedPageId !== null ? pageTabId(selectedPageId) : undefined">
				<GridPage v-if="activePage"
					:key="activePage.id"
					:context="activeContext"
					:page="activePage"
					:can-edit="canManage"
					@saved="page => activePage = page"
					@edit-details="openPageForm"
					@delete="deletePage" />
				<div v-else-if="pageLoading" class="icon-loading" />
				<ErrorMessage v-else :message="t('tables', 'This page could not be found')" />
			</div>
		</div>

		<ErrorMessage v-else-if="errorMessage" :message="errorMessage" />

		<ContextPageForm :show-modal="pageFormOpen"
			:page="pageBeingEdited"
			:pending="pageFormPending"
			@close="closePageForm"
			@submit="submitPageForm" />
		<MainModals />
	</div>
</template>

<script>
import MainModals from '../modules/modals/Modals.vue'
import { mapState, mapActions } from 'pinia'
import { NcIconSvgWrapper } from '@nextcloud/vue'
import { emit } from '@nextcloud/event-bus'
import { NODE_TYPE_TABLE, NODE_TYPE_VIEW } from '../shared/constants.ts'
import svgHelper from '../shared/components/ncIconPicker/mixins/svgHelper.js'
import { useTablesStore } from '../store/store.js'
import ErrorMessage from '../modules/main/partials/ErrorMessage.vue'
import ContextPageBar from '../modules/grid/ContextPageBar.vue'
import GridPage from '../modules/grid/GridPage.vue'
import ContextPageForm from '../modules/modals/ContextPageForm.vue'
import { contextPages, isStartPage, pageTabId, pageTitle } from '../modules/grid/gridLayout.js'
import permissionsMixin from '../shared/components/ncTable/mixins/permissionsMixin.js'
import displayError, { getNotFoundError, getGenericLoadError } from '../shared/utils/displayError.js'
import { getDialogBuilder } from '@nextcloud/dialogs'

export default {
	components: {
		MainModals,
		NcIconSvgWrapper,
		ErrorMessage,
		ContextPageBar,
		GridPage,
		ContextPageForm,
	},

	mixins: [svgHelper, permissionsMixin],

	data() {
		return {
			loading: true,
			icon: null,
			context: null,
			errorMessage: null,
			loadedSignature: null,
			isReloading: false,
			loadedContextId: null,
			reloadAgain: false,
			activePage: null,
			pageLoading: false,
			pageFormOpen: false,
			pageFormPending: false,
			pagePanelId: 'context-page-panel',
			pageBeingEdited: null,
		}
	},

	computed: {
		...mapState(useTablesStore, ['contexts', 'activeContextId', 'activeContext']),
		// The page shown: the grid page in the route, else the start page
		selectedPageId() {
			if (this.$route.params.pageId) {
				return parseInt(this.$route.params.pageId)
			}
			return contextPages(this.activeContext).find(isStartPage)?.id ?? null
		},
		canManage() {
			return this.ownsContext(this.activeContext)
		},
	},

	watch: {

		activeContext: {
			handler() {
				if (this.errorMessage && this.loadedContextId === this.activeContextId) {
					// Already showing an error, don't redirect
					return
				}
				const signature = this.contextSignature()
				if (signature === this.loadedSignature) {
					return
				}
				this.loadedSignature = signature
				this.reload()
			},
		},
		// Another application loads its page itself, when it is loaded
		$route(to, from) {
			if (to.params.contextId === from.params.contextId && to.params.pageId !== from.params.pageId) {
				this.loadActivePage()
			}
		},
		'context.iconName': {
			async handler(value) {
				this.icon = value ? await this.getContextIcon(value) : ''
			},
			immediate: true,
		},
	},

	created() {
		// Counts the page loads, so a load that a newer one overtook leaves the page alone
		this.pageRequest = 0
	},

	async mounted() {
		emit('toggle-navigation', {
			open: false,
		})
		await this.reload()
	},

	methods: {
		pageTabId,
		...mapActions(useTablesStore, ['loadContext', 'loadContextTable', 'loadContextView', 'loadContextPage', 'createContextPage', 'updateContextPage', 'deleteContextPage']),
		contextSignature() {
			const ctx = this.activeContext
			return ctx ? `${ctx.id}:${Object.keys(ctx.nodes || {}).sort().join(',')}` : null
		},
		async reload() {
			if (!this.activeContextId) {
				return
			}

			// A reload asked for while one runs follows it, so it sees the latest application
			if (this.isReloading) {
				this.reloadAgain = true
				return
			}
			this.isReloading = true
			// The same application is refreshed in place, so an edit of its page goes on;
			// another one starts without the page and the error of the previous one
			if (this.loadedContextId !== this.activeContextId) {
				this.loadedContextId = this.activeContextId
				this.activePage = null
				this.errorMessage = null
			}
			if (!this.context || parseInt(this.context.id) !== parseInt(this.activeContextId) || this.errorMessage) {
				this.loading = true
			}

			try {
				await this.loadContext({ id: this.activeContextId })
				const index = this.contexts.findIndex(c => parseInt(c.id) === parseInt(this.activeContextId))
				this.context = this.contexts[index]

				if (!this.context) {
					this.errorMessage = t('tables', 'This application could not be found')
					return
				}

				this.loadedSignature = this.contextSignature()

				this.icon = await this.getContextIcon(this.activeContext.iconName)

				await this.loadActivePage()
			} catch (e) {
				if (e.message === 'NOT_FOUND') {
					this.errorMessage = getNotFoundError('application')
				} else {
					this.errorMessage = getGenericLoadError('application')
					displayError(e, this.errorMessage)
				}
			} finally {
				this.loading = false
				this.isReloading = false
				if (this.reloadAgain) {
					this.reloadAgain = false
					await this.reload()
				}
			}
		},
		/**
		 * Loads the page shown with its widgets, and the tables and views of the application
		 * so data widgets can show their titles.
		 */
		async loadActivePage() {
			const pageId = this.selectedPageId
			if (pageId === null) {
				return
			}
			// The page shown stays until the next one is loaded, so its edit mode keeps its
			// draft and its widgets do not mount again for nothing
			this.pageLoading = true
			const request = ++this.pageRequest
			try {
				const page = await this.loadContextPage({ contextId: this.activeContextId, pageId })
				if (request === this.pageRequest && pageId === this.selectedPageId) {
					this.activePage = page
				}
			} catch (e) {
				if (request !== this.pageRequest) {
					return
				}
				if (e?.response?.status !== 404) {
					displayError(e, t('tables', 'Could not load the page.'))
				} else {
					this.activePage = null
				}
			} finally {
				if (request === this.pageRequest) {
					this.pageLoading = false
				}
			}
			if (request === this.pageRequest) {
				await this.loadApplicationNodes()
			}
		},
		/**
		 * Puts the tables and views of the application in the store, without their rows.
		 */
		async loadApplicationNodes() {
			for (const node of Object.values(this.activeContext?.nodes ?? {})) {
				try {
					if (parseInt(node.node_type) === NODE_TYPE_TABLE) {
						await this.loadContextTable({ id: node.node_id })
					} else if (parseInt(node.node_type) === NODE_TYPE_VIEW) {
						await this.loadContextView({ id: node.node_id })
					}
				} catch {
					// A table or view that cannot be loaded is shown by id in the widget dialog
				}
			}
		},
		/**
		 * Opens a page from the page bar.
		 *
		 * @param {object} page a page of the application
		 */
		openPage(page) {
			if (isStartPage(page)) {
				this.$router.push({ name: 'context', params: { contextId: this.activeContextId } })
			} else {
				this.$router.push({ name: 'contextPage', params: { contextId: this.activeContextId, pageId: page.id } })
			}
		},
		/**
		 * @param {object|null} page the grid page to edit, or null to add one
		 */
		openPageForm(page) {
			this.pageBeingEdited = page
			this.pageFormOpen = true
		},
		closePageForm() {
			this.pageFormOpen = false
			this.pageBeingEdited = null
		},
		/**
		 * Adds a grid page and opens it, or saves the details of the page being edited.
		 *
		 * @param {{title: string, icon: string, slug?: string, description: string}} data the page fields
		 */
		async submitPageForm(data) {
			const editing = this.pageBeingEdited
			this.pageFormPending = true
			let page
			try {
				page = editing
					? await this.updateContextPage({ contextId: this.activeContextId, pageId: editing.id, data })
					: await this.createContextPage({ contextId: this.activeContextId, data })
			} finally {
				this.pageFormPending = false
			}
			if (!page) {
				return
			}
			this.closePageForm()
			if (editing) {
				this.activePage = page
				return
			}
			await this.$router.push({ name: 'contextPage', params: { contextId: this.activeContextId, pageId: page.id } })
		},
		/**
		 * Deletes a grid page after confirmation and goes back to the start page.
		 *
		 * @param {object} page the page to delete
		 */
		async deletePage(page) {
			let confirmed = false
			try {
				await getDialogBuilder(t('tables', 'Delete page'))
					.setText(t('tables', 'Delete the page "{title}" and its widgets? The tables and views stay in the application.', { title: pageTitle(page) }, undefined, { escape: false }))
					.setSeverity('warning')
					.addButton({ label: t('tables', 'Cancel'), callback: () => {} })
					.addButton({ label: t('tables', 'Delete page'), variant: 'error', callback: () => { confirmed = true } })
					.build()
					.show()
			} catch {
				// Closing the dialog keeps the page
			}
			if (confirmed && await this.deleteContextPage({ contextId: this.activeContextId, pageId: page.id })) {
				await this.$router.push({ name: 'context', params: { contextId: this.activeContextId } })
			}
		},
	},
}

</script>

<style scoped lang="scss">
.context {
	.row.first-row {
		position: sticky;
		inset-inline-start: 0;
		top: 0;
		z-index: 15;
		background-color: var(--color-main-background);
		width: var(--app-content-width, 100%);
	}

	&__title {
		display: inline-flex;
	}

	&__description {
		margin: calc(3 * var(--default-grid-baseline, 4px));
		max-width: 790px;
		// Lines up with the page tabs and the widget texts
		margin-inline-start: calc(32px + 3 * var(--default-grid-baseline));
	}

	&:deep(.icon-vue) {
		min-width: 32px;
		min-height: 32px;
	}
}

:deep(h1) {
	font-size: unset;
	font-size: revert;
}
</style>
