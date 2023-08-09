<template>
	<NcModal v-if="showModal" @close="actionCancel">
		<div class="modal__content">
			<div class="row">
				<div class="col-4">
					<h2 tabindex="0">
						{{ t('tables', 'Edit row') }}
					</h2>
				</div>
			</div>
			<div v-for="column in nonMetaColumns" :key="column.id">
				<ColumnFormComponent
					:column="column"
					:value.sync="localRow[column.id]" />
			</div>
			<div class="row">
				<div class="fix-col-4 space-T" :class="{'justify-between': showDeleteButton, 'end': !showDeleteButton}">
					<div v-if="showDeleteButton">
						<NcButton v-if="!prepareDeleteRow" :aria-label="t('tables', 'Delete')" type="error" @click="prepareDeleteRow = true">
							{{ t('tables', 'Delete') }}
						</NcButton>
						<NcButton v-if="prepareDeleteRow"
							:wide="true"
							:aria-label="t('tables', 'I really want to delete this row!')"
							type="error"
							@click="actionDeleteRow">
							{{ t('tables', 'I really want to delete this row!') }}
						</NcButton>
					</div>
					<NcButton v-if="canUpdateData(activeElement) && !localLoading" :aria-label="t('tables', 'Save')" type="primary" @click="actionConfirm">
						{{ t('tables', 'Save') }}
					</NcButton>
					<div v-if="localLoading" class="icon-loading" style="margin-left: 20px;" />
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcButton } from '@nextcloud/vue'
import { showError, showWarning } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'
import ColumnFormComponent from '../main/partials/ColumnFormComponent.vue'
import permissionsMixin from '../../shared/components/ncTable/mixins/permissionsMixin.js'
import { mapGetters } from 'vuex'

export default {
	name: 'EditRow',
	components: {
		NcModal,
		NcButton,
		ColumnFormComponent,
	},
	mixins: [permissionsMixin],
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		columns: {
			type: Array,
			default: null,
		},
		row: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			localRow: {},
			prepareDeleteRow: false,
			localLoading: false,
		}
	},
	computed: {
		...mapGetters(['activeElement', 'isView']),
		showDeleteButton() {
			return this.canDeleteData(this.activeElement) && !this.localLoading
		},
		nonMetaColumns() {
			return this.columns.filter(col => col.id >= 0)
		},
	},
	watch: {
		row() {
			if (this.row) {
				if (this.$router.currentRoute.path.includes('/row/')) {
					this.$router.replace(this.$router.currentRoute.path.split('/row/')[0])
				}
				this.$router.push(this.$router.currentRoute.path + '/row/' + this.row.id)
				this.$store.commit('setActiveRowId', null)
				this.loadValues()
			}
		},
	},
	methods: {
		loadValues() {
			if (this.row) {
				const tmp = {}
				this.row.data.forEach(item => {
					tmp[item.columnId] = item.value
				})
				this.localRow = Object.assign({}, tmp)
			}
		},
		actionCancel() {
			this.$router.back()
			this.reset()
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
					const validValue = this.isValueValidForColumn(this.localRow[col.id], col)
					mandatoryFieldsEmpty = mandatoryFieldsEmpty || !validValue
				}
			})
			if (!mandatoryFieldsEmpty) {
				this.localLoading = true
				await this.sendRowToBE()
				this.localLoading = false
				this.actionCancel()
			} else {
				showWarning(t('tables', 'Please fill in the mandatory fields.'))
			}
		},
		async sendRowToBE() {
			const data = []
			for (const [key, value] of Object.entries(this.localRow)) {
				data.push({
					columnId: key,
					value,
				})
			}
			const res = await this.$store.dispatch('updateRow', {
				id: this.row.id,
				viewId: this.isView ? this.activeElement.id : null,
				data,
			})
			if (!res) {
				showError(t('tables', 'Could not update row'))
			}
		},
		reset() {
			this.localRow = {}
			this.dataLoaded = false
			this.prepareDeleteRow = false
		},
		actionDeleteRow() {
			this.deleteRowAtBE(this.row.id)
		},
		async deleteRowAtBE(rowId) {
			this.localLoading = true
			const res = await this.$store.dispatch('removeRow', {
				rowId,
				viewId: this.isView ? this.activeElement.id : null,
			})
			if (!res) {
				showError(t('tables', 'Could not delete row.'))
			}
			this.localLoading = false
			this.actionCancel()
		},
	},
}
</script>
