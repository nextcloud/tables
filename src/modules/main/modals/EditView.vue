<template>
	<NcModal v-if="showModal" class="edit-view-modal" @close="actionCancel">
		<div class="modal__content">
			<div class="row">
				<div class="col-4">
					<h2 style="padding: 0">
						{{ t('tables', 'Edit view') }}
					</h2>
				</div>
			</div>
			<div v-if="columns === null" class="icon-loading" />

			<div v-else>
				<!--title & emoji-->
				<div class="row space-T">
					<div class="col-4 mandatory">
						{{ t('tables', 'Title') }}
					</div>
					<div class="col-4" style="display: inline-flex;">
						<NcEmojiPicker :close-on-select="true" @select="setIcon">
							<NcButton type="tertiary"
								:aria-label="t('tables', 'Select emoji for view')"
								:title="t('tables', 'Select emoji')"
								@click.prevent>
								{{ icon }}
							</NcButton>
						</NcEmojiPicker>
						<input v-model="title"
							:class="{missing: errorTitle}"
							type="text"
							:placeholder="t('tables', 'Title of the new view')">
					</div>
				</div>
				<!--columns & order-->
				<div class="row space-T">
					<SelectedViewColumns
						:columns="columns"
						:selected-columns="selectedColumns" />
				</div>
				<!--filtering-->
				<div class="row space-T">
					<FilterForm :filters="view.filter" :columns="columns" />
				</div>
				<!--sorting-->
			</div>
			<!-- <div class="row">
				<div class="fix-col-4 space-T" :class="{'justify-between': showDeleteButton, 'end': !showDeleteButton}">
					<div v-if="showDeleteButton">
						<NcButton v-if="!prepareDeleteRow" type="error" @click="prepareDeleteRow = true">
							{{ t('tables', 'Delete') }}
						</NcButton>
						<NcButton v-if="prepareDeleteRow"
							:wide="true"
							type="error"
							@click="actionDeleteView">
							{{ t('tables', 'I really want to delete this view!') }}
						</NcButton>
					</div>
					<NcButton v-if="canUpdateDataActiveTable && !localLoading" type="primary" @click="actionConfirm">
						{{ t('tables', 'Save') }}
					</NcButton>
					<div v-if="localLoading" class="icon-loading" style="margin-left: 20px;" />
				</div>
			</div> -->

			<div class="row">
				<div class="fix-col-4 space-T end">
					<button v-if="!localLoading" class="primary" :aria-label="t('tables', 'Save View')" @click="actionConfirm()">
						{{ t('tables', 'Save') }}
					</button>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcEmojiPicker, NcButton } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'
import { mapGetters } from 'vuex'
import tablePermissions from '../mixins/tablePermissions.js'
import FilterForm from '../partials/editViewPartials/filter/FilterForm.vue'
import SelectedViewColumns from '../partials/editViewPartials/SelectedViewColumns.vue'

export default {
	name: 'EditView',
	components: {
		NcModal,
		NcEmojiPicker,
		NcButton,
		FilterForm,
		SelectedViewColumns,
	},
	mixins: [tablePermissions],
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		view: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			title: '',
			icon: '',
			errorTitle: false,
			selectedColumns: [],
			localLoading: false,
			prepareDelete: false,
			columns: null,
			draggedItem: null,
			startDragIndex: null,
		}
	},
	computed: {
		...mapGetters(['activeTable']),
		showDeleteButton() {
			return true // TODO
			// return this.canDeleteDataActiveTable && !this.localLoading
		},
	},
	watch: {
		title() {
			if (this.title.length >= 200) {
				showError(t('tables', 'The title limit is reached with 200 characters. Please use a shorter title.'))
				this.title = this.title.slice(0, 199)
			}
		},
		async showModal() {
			this.reset()
			await this.loadTableColumnsFromBE()
		},
	},
	methods: {
		setIcon(icon) {
			this.icon = icon
		},
		actionCancel() {
			this.reset()
			this.$emit('close')
		},
		async loadTableColumnsFromBE() {
			this.columns = await this.$store.dispatch('getColumnsFromBE', { tableId: this.view.tableId })
			// Show columns of view first
			this.columns.sort((a, b) => {
				const aSelected = this.selectedColumns.includes(a.id)
				const bSelected = this.selectedColumns.includes(b.id)
				if (aSelected && !bSelected) {
					return -1
				} else if (!aSelected && bSelected) {
					return 1
				} else {
					return 0
				}
			})
		},
		async actionConfirm() {
			// TODO: Validate filter
			if (this.title === '') {
				showError(t('tables', 'Cannot update view. Title is missing.'))
				this.errorTitle = true
			} else {
				this.localLoading = true
				const success = await this.updateViewToBE(this.view.id)
				this.localLoading = false
				if (success) {
					this.actionCancel()
				}
			}
		},
		async updateViewToBE(id) {
			const newSelectedColumnIds = this.columns.map(col => col.id).filter(id => this.selectedColumns.includes(id))
			const data = {
				data: {
					title: this.title,
					emoji: this.icon,
					columns: JSON.stringify(newSelectedColumnIds),
					filter: JSON.stringify(this.view.filter),
				},
			}
			const res = await this.$store.dispatch('updateView', { id, data })
			if (res) {
				console.debug(res, this.view)
				// TODO: Only reload if something changed
				this.$emit('reload-view')
				return res
			} else {
				showError(t('tables', 'Could not update view'))
			}
		},
		reset() {
			this.title = this.view.title
			this.icon = this.view.emoji
			this.errorTitle = false
			this.selectedColumns = [...this.view.columns]
			this.localLoading = false
			this.columns = null
		},
	},
}
</script>

<style lang="scss" scoped>

:deep(.modal-container) {
	min-width: 60% !important;
}

</style>
