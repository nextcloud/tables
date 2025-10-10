<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="showModal"
		:name="t('tables', 'Create row')"
		size="large"
		data-cy="createRowModal"
		@closing="actionCancel">
		<div class="modal__content" @keydown="onKeydown">
			<div v-for="column in nonMetaColumns" :key="column.id" :data-cy="column.title">
				<ColumnFormComponent
					:column="column"
					:value.sync="row[column.id]" />
				<NcNoteCard v-if="isMandatory(column) && !isValueValidForColumn(row[column.id], column)"
					type="error">
					{{ t('tables', '"{columnTitle}" should not be empty', { columnTitle: column.title }) }}
				</NcNoteCard>
				<NcNoteCard v-if="row[column.id] && column.type === 'text-link' && !isValidUrlProtocol(row[column.id])"
					type="error">
					{{ t('tables', 'Invalid protocol. Allowed: {allowed}', {allowed: allowedProtocols.join(', ')}) }}
				</NcNoteCard>
			</div>
			<div class="row">
				<div class="fix-col-4 space-T end">
					<div class="padding-right">
						<NcCheckboxRadioSwitch :checked.sync="addNewAfterSave" type="switch" data-cy="createRowAddMoreSwitch">
							{{ t('tables', 'Add more') }}
						</NcCheckboxRadioSwitch>
					</div>
					<NcButton v-if="!localLoading" class="primary" :aria-label="t('tables', 'Save row')" :disabled="hasEmptyMandatoryRows || hasInvalidUrlProtocol" data-cy="createRowSaveButton" @click="actionConfirm()">
						{{ t('tables', 'Save') }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcDialog>
</template>

<script>
import { NcDialog, NcCheckboxRadioSwitch, NcNoteCard, NcButton } from '@nextcloud/vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import ColumnFormComponent from '../main/partials/ColumnFormComponent.vue'
import { translate as t } from '@nextcloud/l10n'
import rowHelper from '../../shared/components/ncTable/mixins/rowHelper.js'
import { useDataStore } from '../../store/data.js'
import { mapActions } from 'pinia'
import { ALLOWED_PROTOCOLS } from '../../shared/constants.ts'

export default {
	name: 'CreateRow',
	components: {
		NcDialog,
		ColumnFormComponent,
		NcCheckboxRadioSwitch,
		NcNoteCard,
		NcButton,
	},
	mixins: [rowHelper],
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
			allowedProtocols: ALLOWED_PROTOCOLS,
		}
	},
	computed: {
		nonMetaColumns() {
			return this.columns.filter(col => col.id >= 0)
		},
		hasEmptyMandatoryRows() {
			return this.checkMandatoryFields(this.row)
		},
		hasInvalidUrlProtocol() {
			return this.nonMetaColumns.some(col => col.type === 'text-link' && !this.isValidUrlProtocol(this.row[col.id]))
		},
	},
	watch: {
		showModal() {
			if (this.showModal) {
				this.$nextTick(() => {
					this.$el.querySelector('input')?.focus()
				})
			}
		},
	},
	methods: {
		...mapActions(useDataStore, ['insertNewRow']),
		t,
		actionCancel() {
			this.reset()
			this.addNewAfterSave = false
			this.$emit('close')
		},
		async actionConfirm() {
			this.localLoading = true
			const success = await this.sendNewRowToBE()
			this.localLoading = false
			// If the row was not created, we don't want to close the modal
			if (!success) {
				return
			}
			if (!this.addNewAfterSave) {
				this.actionCancel()
			} else {
				showSuccess(t('tables', 'Row successfully created.'))
				this.reset()
			}
		},
		async sendNewRowToBE() {
			if (!this.tablesStore) {
				const { default: store } = await import('../../store/store.js')
				this.tablesStore = store
			}

			try {
				const data = {}
				for (const [key, value] of Object.entries(this.row)) {
					data[key] = value
				}
				return await this.insertNewRow({
					viewId: this.isView ? this.elementId : null,
					tableId: !this.isView ? this.elementId : null,
					data,
				})
			} catch (e) {
				console.error(e)
				showError(t('tables', 'Could not create new row'))
				return false
			}
		},
		reset() {
			this.row = {}
		},
		onKeydown(event) {
			if (event.key === 'Enter' && event.ctrlKey) {
				this.actionConfirm()
			}
		},
	},
}
</script>
<style lang="scss" scoped>
.modal-mask {
	z-index: 9999;
}

.modal__content {
	padding: 20px;

	.row .space-T,
	.row.space-T {
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
		display: inline-block;
	}

	:where(.slot.fix-col-4 input, .slot.fix-col-4 .row) {
		min-width: 100% !important;
	}

	:where(.name-parts) {
		display: block !important;
		max-width: fit-content !important;
	}
}

.padding-right {
	padding-inline-end: calc(var(--default-grid-baseline) * 3);
}

</style>
