<template>
	<NcModal v-if="showModal" @close="actionCancel">
		<div class="modal__content">
			<div class="row">
				<div class="col-4">
					<h2 style="padding: 0" tabindex="0">
						{{ t('tables', 'Create row') }}
					</h2>
				</div>
			</div>
			<div v-for="column in nonMetaColumns" :key="column.id">
				<ColumnFormComponent
					:column="column"
					:value.sync="row[column.id]" />
				<NcNoteCard v-if="column.mandatory && !isValueValidForColumn(row[column.id], column)"
					type="error">
					{{ t('tables', `"${column.title}" should not be empty`) }}
				</NcNoteCard>
			</div>
			<div class="row">
				<div class="fix-col-4 space-T end">
					<div class="padding-right">
						<NcCheckboxRadioSwitch :checked.sync="addNewAfterSave" type="switch">
							{{ t('tables', 'Add more') }}
						</NcCheckboxRadioSwitch>
					</div>
					<button v-if="!localLoading" class="primary" :aria-label="t('tables', 'Save row')" :disabled="hasEmptyMandatoryRows" @click="actionConfirm()">
						{{ t('tables', 'Save') }}
					</button>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcCheckboxRadioSwitch, NcNoteCard } from '@nextcloud/vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'
import { mapGetters } from 'vuex'
import ColumnFormComponent from '../main/partials/ColumnFormComponent.vue'

export default {
	name: 'CreateRow',
	components: {
		NcModal,
		ColumnFormComponent,
		NcCheckboxRadioSwitch,
		NcNoteCard,
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
	},
	data() {
		return {
			row: {},
			localLoading: false,
			addNewAfterSave: false,
		}
	},
	computed: {
		...mapGetters(['activeElement', 'isView']),
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
					viewId: this.isView ? this.activeElement.id : null,
					tableId: !this.isView ? this.activeElement.id : null,
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
