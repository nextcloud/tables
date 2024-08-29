<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcModal v-if="showModal" data-cy="createRowModal" @close="actionCancel">
		<div class="modal__content" @keydown="onKeydown">
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
import { translate as t } from '@nextcloud/l10n'
import rowHelper from '../../shared/components/ncTable/mixins/rowHelper.js'

export default {
	name: 'CreateRow',
	components: {
		NcModal,
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
		}
	},
	computed: {
		nonMetaColumns() {
			return this.columns.filter(col => col.id >= 0)
		},
		hasEmptyMandatoryRows() {
			return this.checkMandatoryFields(this.row)
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
		t,
		actionCancel() {
			this.reset()
			this.addNewAfterSave = false
			this.$emit('close')
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
			if (!this.$store) {
				const { default: store } = await import(/* webpackChunkName: 'store' */ '../../store/store.js')
				this.$store = store
			}

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
	z-index: 2001;
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

	:where(.fix-col-1.end) {
		display: inline-block;
		position: relative;
		left: 65%;
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
	padding-right: calc(var(--default-grid-baseline) * 3);
}

</style>
