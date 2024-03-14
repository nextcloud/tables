<template>
	<NcModal v-if="showModal" data-cy="createRowModal" @close="actionCancel">
		<div class="modal__content">
			<div class="row">
				<div class="col-4">
					<h2 style="padding: 0" tabindex="0">
						{{ t('tables', 'Create row') }}
					</h2>
				</div>
			</div>
			<div v-for="column in nonMetaColumns" :key="column.id" :data-cy="column.title">
				<ColumnFormComponent
					:column="column"
					:value.sync="row[column.id]" />
				<NcNoteCard v-if="column.mandatory && !isValueValidForColumn(row[column.id], column)"
					type="error">
					{{ t('tables', '"{columnTitle}" should not be empty', { columnTitle: column.title }) }}
				</NcNoteCard>
			</div>
			<div class="row">
				<div class="fix-col-4 space-T end">
					<div class="padding-right">
						<NcCheckboxRadioSwitch :checked.sync="addNewAfterSave" type="switch" data-cy="createRowAddMoreSwitch">
							{{ t('tables', 'Add more') }}
						</NcCheckboxRadioSwitch>
					</div>
					<NcButton v-if="!localLoading" class="primary" :aria-label="t('tables', 'Save row')" :disabled="hasEmptyMandatoryRows" data-cy="createRowSaveButton" @click="actionConfirm()">
						{{ t('tables', 'Save') }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcCheckboxRadioSwitch, NcNoteCard, NcButton } from '@nextcloud/vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import ColumnFormComponent from '../main/partials/ColumnFormComponent.vue'

export default {
	name: 'CreateRow',
	components: {
		NcModal,
		ColumnFormComponent,
		NcCheckboxRadioSwitch,
		NcNoteCard,
		NcButton,
	},
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		columns: {
			type: Array,
			default: null,
		},
		isView: {
			type: Boolean,
			default: false,
		},
		elementId: {
			type: Number,
			default: null,
		},
	},
	data() {
		return {
			row: {},
			localLoading: false,
			addNewAfterSave: false,
		}
	},
	computed: {
		nonMetaColumns() {
			return this.columns.filter(col => col.id >= 0)
		},
		hasEmptyMandatoryRows() {
			let mandatoryFieldsEmpty = false
			this.columns.forEach(col => {
				if (col.mandatory) {
					const validValue = this.isValueValidForColumn(this.row[col.id], col)
					mandatoryFieldsEmpty = mandatoryFieldsEmpty || !validValue
				}
			})
			return mandatoryFieldsEmpty
		},
	},
	methods: {
		actionCancel() {
			this.reset()
			this.addNewAfterSave = false
			this.$emit('close')
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
		async actionConfirm() {
			this.localLoading = true
			await this.sendNewRowToBE()
			this.localLoading = false
			if (!this.addNewAfterSave) {
				this.actionCancel()
			} else {
				showSuccess(t('tables', 'Row successfully created.'))
				this.reset()
			}
		},
		async sendNewRowToBE() {
			try {
				const data = []
				for (const [key, value] of Object.entries(this.row)) {
					data.push({
						columnId: key,
						value,
					})
				}
				await this.$store.dispatch('insertNewRow', {
					viewId: this.isView ? this.elementId : null,
					tableId: !this.isView ? this.elementId : null,
					data,
				})
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not create new row'))
			}
		},
		reset() {
			this.row = {}
		},
	},
}
</script>
<style lang="scss" scoped>

.padding-right {
	padding-right: calc(var(--default-grid-baseline) * 3);
}

</style>
