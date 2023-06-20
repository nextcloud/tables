<template>
	<NcModal v-if="showModal" @close="actionCancel">
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
				<div class="col-4 mandatory">
					{{ t('tables', 'Columns to be displayed') }}
				</div>
				<div v-for="column in columns" :key="column.id" style="display: flex; align-items: center;">
					<NcCheckboxRadioSwitch
						:checked="selectedColumns.includes(column.id)"
						style="padding-right: 10px;"
						@update:checked="onToggle(column.id)" />
					{{ column.title }}
				</div>
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
import { NcModal, NcEmojiPicker, NcButton, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'
import { mapGetters } from 'vuex'
import tablePermissions from '../mixins/tablePermissions.js'

export default {
	name: 'EditView',
	components: {
		NcModal,
		NcEmojiPicker,
		NcButton,
		NcCheckboxRadioSwitch,
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
		onToggle(columnId) {
			if (this.selectedColumns.includes(columnId)) {
				this.selectedColumns.splice(this.selectedColumns.indexOf(columnId), 1)
			} else {
				this.selectedColumns.push(columnId)
			}
		},
		isValueValidForColumn(value, column) {
			if (column.type === 'selection') {
				if (
					(value instanceof Array && value.length > 0)
					|| (value === parseInt(value))
				) {
					return true
				}
				return false
			}
			return !!value || value === 0
		},
		async loadTableColumnsFromBE() {
			this.columns = await this.$store.dispatch('getColumnsFromBE', { tableId: this.view.tableId })
		},
		async actionConfirm() {
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
			const data = {
				data: {
					title: this.title,
					emoji: this.icon,
					columns: JSON.stringify(this.selectedColumns),
				},
			}
			const res = await this.$store.dispatch('updateView', { id, data })
			if (res) {
				console.debug(res, this.view)
				if (this.selectedColumns !== this.view.columns) {
					await this.$store.dispatch('loadColumnsFromBE', { viewId: this.view.id })
				}
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
