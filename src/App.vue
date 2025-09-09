<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcContent app-name="tables">
		<Navigation />
		<NcAppContent>
			<div v-if="isLoadingSomething" class="icon-loading" />

			<router-view v-if="!isLoadingSomething" />
		</NcAppContent>
		<Sidebar />
	</NcContent>
</template>

<script>
import { NcContent, NcAppContent } from '@nextcloud/vue'
import Navigation from './modules/navigation/sections/Navigation.vue'
import { mapState, mapActions } from 'pinia'
import Sidebar from './modules/sidebar/sections/Sidebar.vue'
import { useResizeObserver } from '@vueuse/core'
import { loadState } from '@nextcloud/initial-state'
import { useTablesStore } from './store/store.js'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'App',
	components: {
		Sidebar,
		NcContent,
		NcAppContent,
		Navigation,
	},
	props: {
		tableId: {
			type: Number,
			default: null,
		},
	},
	data() {
		return {
			loading: false,
			defaultPageTitle: false,
		}
	},
	computed: {
		...mapState(useTablesStore, ['isLoadingSomething', 'activeView', 'activeTable', 'activeContext']),
	},
	watch: {
		'$route'(to) {
			this.routing(to)
		},
	},
	async created() {
		const store = useTablesStore()
		await Promise.all([
			store.loadTablesFromBE(),
			store.getAllContexts(),
			store.loadViewsSharedWithMeFromBE(),
			store.loadTemplatesFromBE(),
		])
		this.routing(this.$router.currentRoute)
		this.observeAppContent()
	},
	methods: {
		...mapActions(useTablesStore, ['loadTablesFromBE', 'getAllContexts', 'loadViewsSharedWithMeFromBE', 'loadTemplatesFromBE', 'setActiveRowId', 'setActiveTableId', 'setActiveViewId', 'setActiveContextId']),
		routing(currentRoute) {
			const url = generateUrl('/apps/tables/')

			try {
				if (loadState('tables', 'contextId', undefined)) {
					// prepare route, when Context is opened from navigation bar
					const contextId = loadState('tables', 'contextId', undefined)
					const originalUrl = window.location.href
					this.$router.replace('/application/' + contextId).catch(() => {})
					// reverts turning /apps/tables/app/28 into /apps/tables/app/28#/application/28
					history.replaceState({}, undefined, originalUrl)
				}
			} catch (e) {
				// contextId is not always set, it is fine.
			}

			if (currentRoute.name === 'tableRow' || currentRoute.name === 'viewRow') {
				this.setActiveRowId(parseInt(currentRoute.params.rowId))
			} else {
				this.setActiveRowId(null)
			}
			if (currentRoute.path.startsWith('/table/')) {
				this.setActiveTableId(parseInt(currentRoute.params.tableId))
				const tableName = this.activeTable?.title || t('tables', 'Table')
				this.setPageTitle(tableName)
				if (!currentRoute.path.includes('/row/')) {
					const targetElement = document.querySelector(`header .header-start .app-menu a[href="${url}"]`)
						|| document.querySelector(`header .header-left .app-menu a[href="${url}"]`)
					this.switchActiveMenuEntry(targetElement)
				}
			} else if (currentRoute.path.startsWith('/view/')) {
				this.setActiveViewId(parseInt(currentRoute.params.viewId))
				const viewName = this.activeView?.title || t('tables', 'View')
				this.setPageTitle(viewName)
				if (!currentRoute.path.includes('/row/')) {
					const targetElement = document.querySelector(`header .header-start .app-menu a[href="${url}"]`)
						|| document.querySelector(`header .header-left .app-menu a[href="${url}"]`)
					this.switchActiveMenuEntry(targetElement)
				}
			} else if (currentRoute.path.startsWith('/application/')) {
				const contextId = parseInt(currentRoute.params.contextId)
				this.setActiveContextId(contextId)
				const contextName = this.activeContext?.name || t('tables', 'Tables')
				this.setPageTitle(contextName)

				// This breaks if there are multiple contexts with the same name or another app has the same name. We need a better way to identify the correct element.
				const targetElement = document.querySelector(`header .header-start .app-menu [title="${contextName}"]`)
					|| document.querySelector(`header .header-left .app-menu [title="${contextName}"]`)
				if (targetElement) {
					this.switchActiveMenuEntry(targetElement)
				}

				// move the focus away from nav bar (import for app-internal switch)
				const appContent = document.getElementById('app-content-vue')
				const oldTabIndex = appContent.tabIndex
				if (oldTabIndex === -1) {
					appContent.tabIndex = 0
				}
				appContent.focus()
				appContent.tabIndex = oldTabIndex
			}
		},
		switchActiveMenuEntry(targetElement) {
			targetElement = targetElement?.tagName?.toLowerCase() === 'a' ? targetElement.parentElement : targetElement
			const currentlyActive = document.querySelector('header .header-start .app-menu li.app-menu-entry--active') || document.querySelector('header .header-left .app-menu li.app-menu-entry--active')
			currentlyActive?.classList.remove('app-menu-entry--active')
			targetElement?.classList.add('app-menu-entry--active')
		},
		setPageTitle(title) {
			if (this.defaultPageTitle === false) {
				const appTitle = t('tables', 'Tables')
				this.defaultPageTitle = window.document.title
				if (this.defaultPageTitle.indexOf(` - ${appTitle} - `) !== -1) {
					this.defaultPageTitle = this.defaultPageTitle.substring(this.defaultPageTitle.indexOf(` - ${appTitle} - `) + 3)
				}
				if (this.defaultPageTitle.indexOf(`${appTitle} - `) !== 0) {
					this.defaultPageTitle = `${appTitle} - ` + this.defaultPageTitle
				}
			}
			let newTitle = this.defaultPageTitle
			if (title !== '') {
				newTitle = `${title} - ${newTitle}`
			}
			window.document.title = newTitle
		},
		observeAppContent() {
			useResizeObserver(document.getElementById('app-content-vue'), (entries) => {
				const entry = entries[0]
				const { width } = entry.contentRect
				document.documentElement.style.setProperty('--app-content-width', `${width}px`)
			})
		},
	},
}
</script>
<style scoped lang="scss">

	.sidebar-icon {
		position: absolute;
		inset-inline-end: 5px;
		top: 5px;
	}

</style>
<style lang="scss" scoped>

h1 {
	font-size: 1.98em;
}

h2 {
	font-size: larger;
}

h3 {
	font-size: large;
}

h4 {
	font-size: medium;
	font-weight: 300;
}

p, .p {
	padding-top: 5px;
	padding-bottom: 7px;
}

.editor-wrapper p, .editor-wrapper .p {
	padding: 0;
}

p span, .p span, p .span, .p .span, .p.span, p.span, .light {
	color: var(--color-text-maxcontrast);
}

p code {
	white-space: pre-wrap;
	background-color: var(--color-background-dark);
	border-radius: var(--border-radius);
	padding: 0.8em 1em;
	margin-bottom: 0.8em;
	font-family: 'Lucida Console', 'Lucida Sans Typewriter', 'DejaVu Sans Mono', monospace;
}

.bold {
	font-weight: bold;
}

.light {
	font-weight: 100;
	color: var(--color-text-lighter);
	/*
      background-color: var(--color-background-hover);
      */
}

button[class^='icon-'] {
	min-width: 36px !important;
}

[class^='col-'] > span {
	color: var(--color-text-maxcontrast);
}

.icon-left {
	background-position: left;
	padding-inline-start: 22px;
}

.mandatory {
	font-weight: bold;
}

.v-popover button {
	height: 25px;
	margin-inline-start: 10px;
	background-color: transparent;
	border: none;
}

.popover__inner p, .v-popper__inner table {
	padding: 15px;
}

.v-popper__inner table td {
	padding-inline-end: 15px;
}

.error {
	color:  var(--color-error);
}

.error input {
	border-color: var(--color-error);
}

.icon-loading:first-child {
	top: 10vh;
}

.block {
	display: block !important;
}

.align-right {
	text-align: end;
}

.inline-flex {
	display: inline-flex;
}

.v-select.select .vs__selected-options {
    min-height: calc(var(--default-clickable-area) - 2 * var(--vs-border-width)) !important;
    padding: 0 5px;
}

</style>
