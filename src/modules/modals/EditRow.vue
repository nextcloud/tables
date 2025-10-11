<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="showModal"
		data-cy="editRowModal"
		:name="t('tables', 'Edit row')"
		size="large"
		@closing="actionCancel">
		<div class="modal__content" @keydown="onKeydown">
			<div v-if="isActivityEnabled" class="tabs-navigation">
				<NcButton :aria-label="t('tables', 'Edit')"
					:active="activeTabId === 'edit'"
					:variant="activeTabId === 'edit' ? 'primary' : 'secondary'"
					wide
					@click="activeTabId = 'edit'">
					<template #icon>
						<HomeIcon v-if="activeTabId === 'edit'" />
						<HomeOutlineIcon v-else />
					</template>
					{{ t('tables', 'Edit') }}
				</NcButton>
				<NcButton :aria-label="t('tables', 'Activity')"
					:active="activeTabId === 'activity'"
					:variant="activeTabId === 'activity' ? 'primary' : 'secondary'"
					wide
					@click="activeTabId = 'activity'">
					<template #icon>
						<ActivityIcon />
					</template>
					{{ t('tables', 'Activity') }}
				</NcButton>
			</div>

			<div v-if="activeTabId === 'edit'" class="row">
				<div v-for="column in nonMetaColumns" :key="column.id">
					<ColumnFormComponent
						:column="column"
						:value.sync="localRow[column.id]" />
					<NcNoteCard v-if="isMandatory(column) && !isValueValidForColumn(localRow[column.id], column)"
						type="error">
						{{ t('tables', '"{columnTitle}" should not be empty', { columnTitle: column.title }) }}
					</NcNoteCard>
					<NcNoteCard v-if="localRow[column.id] && column.type === 'text-link' && !isValidUrlProtocol(localRow[column.id])"
						type="error">
						{{ t('tables', 'Invalid protocol. Allowed: {allowed}', {allowed: allowedProtocols.join(', ')}) }}
					</NcNoteCard>
				</div>
				<div class="row">
					<div class="fix-col-4 space-T" :class="{'justify-between': showDeleteButton, 'end': !showDeleteButton}">
						<div v-if="showDeleteButton">
							<NcButton v-if="!prepareDeleteRow" :aria-label="t('tables', 'Delete')" type="error" data-cy="editRowDeleteButton" @click="prepareDeleteRow = true">
								{{ t('tables', 'Delete') }}
							</NcButton>
							<NcButton v-if="prepareDeleteRow"
								data-cy="editRowDeleteConfirmButton"
								:wide="true"
								:aria-label="t('tables', 'I really want to delete this row!')"
								type="error"
								@click="actionDeleteRow">
								{{ t('tables', 'I really want to delete this row!') }}
							</NcButton>
						</div>
						<NcButton v-if="canUpdateData(element) && !localLoading" :aria-label="t('tables', 'Save')" type="primary"
							data-cy="editRowSaveButton"
							:disabled="hasEmptyMandatoryRows || hasInvalidUrlProtocol"
							@click="actionConfirm">
							{{ t('tables', 'Save') }}
						</NcButton>
						<div v-if="localLoading" class="icon-loading" style="margin-left: 20px;" />
					</div>
				</div>
			</div>

			<div v-else-if="activeTabId === 'activity'">
				<ActivityList filter="tables"
					:object-id="row.id"
					object-type="tables_row"
					type="tables" />
			</div>
		</div>
	</NcDialog>
</template>

<script>
import { NcDialog, NcButton, NcNoteCard } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import '@nextcloud/dialogs/style.css'
import ColumnFormComponent from '../main/partials/ColumnFormComponent.vue'
import permissionsMixin from '../../shared/components/ncTable/mixins/permissionsMixin.js'
import rowHelper from '../../shared/components/ncTable/mixins/rowHelper.js'
import { mapActions } from 'pinia'
import { useTablesStore } from '../../store/store.js'
import { useDataStore } from '../../store/data.js'
import { ALLOWED_PROTOCOLS } from '../../shared/constants.ts'
import ActivityIcon from 'vue-material-design-icons/LightningBolt.vue'
import HomeIcon from 'vue-material-design-icons/Home.vue'
import HomeOutlineIcon from 'vue-material-design-icons/HomeOutline.vue'
import ActivityList from '../../shared/components/ActivityList.vue'
import activityMixin from '../../shared/mixins/activityMixin.js'

export default {
	name: 'EditRow',
	components: {
		ActivityList,
		NcDialog,
		NcButton,
		ColumnFormComponent,
		NcNoteCard,
		ActivityIcon,
		HomeIcon,
		HomeOutlineIcon,
	},
	mixins: [permissionsMixin, rowHelper, activityMixin],
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
			allowedProtocols: ALLOWED_PROTOCOLS,
			activeTabId: 'edit',
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
			return this.checkMandatoryFields(this.localRow)
		},
		hasInvalidUrlProtocol() {
			return this.nonMetaColumns.some(col => col.type === 'text-link' && !this.isValidUrlProtocol(this.localRow[col.id]))
		},
	},
	watch: {
		row() {
			if (this.row) {
				if (this.$router.currentRoute.path.includes('/row/')) {
					this.$router.replace(this.$router.currentRoute.path.split('/row/')[0])
				}
				this.$router.push(this.$router.currentRoute.path + '/row/' + this.row.id)
				this.setActiveRowId(null)
				this.loadValues()
			}
		},
	},
	mounted() {
		this.loadValues()
	},
	methods: {
		...mapActions(useDataStore, ['updateRow', 'removeRow']),
		...mapActions(useTablesStore, ['setActiveRowId']),
		t,
		loadValues() {
			if (this.row) {
				const tmp = {}
				this.row.data.forEach(item => {
					tmp[item.columnId] = item.value
				})

				// Ensure all columns have entries, even if missing from row data
				this.columns.forEach(column => {
					if (!(column.id in tmp)) {
						// For usergroup columns, initialize as empty array
						if (column.type === 'usergroup') {
							tmp[column.id] = []
						} else {
							tmp[column.id] = null
						}
					}
				})

				this.localRow = Object.assign({}, tmp)
			}
		},
		actionCancel() {
			// Remove the row path from URL if it exists
			if (this.$router && this.$router.currentRoute.path.includes('/row/')) {
				this.$router.back()
			}
			this.reset()
			this.$emit('close')
		},
		async actionConfirm() {
			this.localLoading = true
			const success = await this.sendRowToBE()
			this.localLoading = false
			// If the row was not created, we don't want to close the modal
			if (!success) {
				return
			}
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

			return await this.updateRow({
				id: this.row.id,
				isView: this.isView,
				elementId: this.element.id,
				data,
			})
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
			const res = await this.removeRow({
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
			if (this.tablesStore) { return }

			const { default: store } = await import('../../store/store.js')
			this.tablesStore = store
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

	:where(.fix-col-1.end) {
		display: flex;
		justify-content: flex-end;
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

.tabs-navigation {
	display: flex;
	gap: 12px;
	margin-bottom: 20px;
}
</style>
