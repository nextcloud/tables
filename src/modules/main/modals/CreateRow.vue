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
			</div>
			<div class="row">
				<div class="fix-col-4 space-T end">
					<div class="padding-right">
						<NcCheckboxRadioSwitch :checked.sync="addNewAfterSave" type="switch">
							{{ t('tables', 'Add more') }}
						</NcCheckboxRadioSwitch>
					</div>
					<button v-if="!localLoading" class="primary" :aria-label="t('tables', 'Save row')" @click="actionConfirm()">
						{{ t('tables', 'Save') }}
					</button>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import { showError, showSuccess, showWarning } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'
import { mapGetters } from 'vuex'
import ColumnFormComponent from '../partials/ColumnFormComponent.vue'

export default {
	name: 'CreateRow',
	components: {
		NcModal,
		ColumnFormComponent,
		NcCheckboxRadioSwitch,
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
			let mandatoryFieldsEmpty = false
			this.columns.forEach(col => {
				if (col.mandatory) {
					const validValue = this.isValueValidForColumn(this.row[col.id], col)
					mandatoryFieldsEmpty = mandatoryFieldsEmpty || !validValue
				}
			})
			if (!mandatoryFieldsEmpty) {
				this.localLoading = true
				await this.sendNewRowToBE()
				this.localLoading = false
				if (!this.addNewAfterSave) {
					this.actionCancel()
				} else {
					showSuccess(t('tables', 'Row successfully created.'))
					this.reset()
				}
			} else {
				showWarning(t('tables', 'Please fill in the mandatory fields.'))
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
