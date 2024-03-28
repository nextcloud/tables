<template>
	<NcModal v-if="showModal" data-cy="editRowModal" @close="actionCancel">
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
				<NcNoteCard v-if="column.mandatory && !isValueValidForColumn(localRow[column.id], column)"
					type="error">
					{{ t('tables', '"{columnTitle}" should not be empty', { columnTitle: column.title }) }}
				</NcNoteCard>
			</div>
			<div class="row">
				<div class="fix-col-4 space-T" :class="{'justify-between': showDeleteButton, 'end': !showDeleteButton}">
					<div v-if="showDeleteButton">
						<NcButton v-if="!prepareDeleteRow" :aria-label="t('tables', 'Delete')" type="error" data-cy="editRowDeleteButton" @click="prepareDeleteRow = true">
							{{ t('tables', 'Delete') }}
						</NcButton>
						<NcButton v-if="prepareDeleteRow"
							data-cy="editRowEditConfirmButton"
							:wide="true"
							:aria-label="t('tables', 'I really want to delete this row!')"
							type="error"
							@click="actionDeleteRow">
							{{ t('tables', 'I really want to delete this row!') }}
						</NcButton>
					</div>
					<NcButton v-if="canUpdateData(element) && !localLoading" :aria-label="t('tables', 'Save')" type="primary"
						data-cy="editRowSaveButton"
						:disabled="hasEmptyMandatoryRows"
						@click="actionConfirm">
						{{ t('tables', 'Save') }}
					</NcButton>
					<div v-if="localLoading" class="icon-loading" style="margin-left: 20px;" />
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcButton, NcNoteCard } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'
import ColumnFormComponent from '../main/partials/ColumnFormComponent.vue'
import permissionsMixin from '../../shared/components/ncTable/mixins/permissionsMixin.js'

export default {
	name: 'EditRow',
	components: {
		NcModal,
		NcButton,
		ColumnFormComponent,
		NcNoteCard,
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
		isView: {
			type: Boolean,
			default: false,
		},
		element: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			localRow: null,
			prepareDeleteRow: false,
			localLoading: false,
		}
	},
	computed: {
		showDeleteButton() {
			return this.canDeleteData(this.element) && !this.localLoading
		},
		nonMetaColumns() {
			return this.columns.filter(col => col.id >= 0)
		},
		hasEmptyMandatoryRows() {
			let mandatoryFieldsEmpty = false
			this.columns.forEach(col => {
				if (col.mandatory) {
					const validValue = this.isValueValidForColumn(this.localRow[col.id], col)
					mandatoryFieldsEmpty = mandatoryFieldsEmpty || !validValue
				}
			})
			return mandatoryFieldsEmpty
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
	mounted() {
		this.loadValues()
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
			this.$router?.back()
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
			this.localLoading = true
			await this.sendRowToBE()
			this.localLoading = false
			this.actionCancel()
		},
		async sendRowToBE() {
			await this.loadStore()

			const data = []
			for (const [key, value] of Object.entries(this.localRow)) {
				data.push({
					columnId: key,
					value: value ?? '',
				})
			}
			const res = await this.$store.dispatch('updateRow', {
				id: this.row.id,
				isView: this.isView,
				elementId: this.element.id,
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
			await this.loadStore()

			this.localLoading = true
			const res = await this.$store.dispatch('removeRow', {
				rowId,
				isView: this.isView,
				elementId: this.element.id,
			})
			if (!res) {
				showError(t('tables', 'Could not delete row.'))
			}
			this.localLoading = false
			this.actionCancel()
		},
		async loadStore() {
			if (this.$store) { return }

			const { default: store } = await import(/* webpackChunkName: 'store' */ '../../store/store.js')
			this.$store = store
		},
	},
}
</script>

<style lang="scss" scoped>
.modal__content {
	padding: 20px;

	:where(.row .space-T, .row.space-T) {
		padding-top: 20px;
	}

	:where([class*='fix-col-']) {
		display: flex;
	}

	:where(.slot) {
		align-items: baseline;
	}

	:where(.end) {
		justify-content: end;
	}

	:where(.slot.fix-col-2) {
		min-width: 50%;
	}

	:where(.fix-col-3) {
		min-width: 75%;
	}

	:where(.slot.fix-col-4 input, .slot.fix-col-4 .row) {
		min-width: 100% !important;
	}
}
</style>
