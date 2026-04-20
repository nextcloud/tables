<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcAppSettingsDialog :open.sync="open"
		:show-navigation="true"
		:title="t('tables', 'Edit table')"
		data-cy="editTableModal">
		<NcAppSettingsSection id="title" :name="t('tables', 'Title')">
			<div class="row">
				<div class="col-3 content-emoji">
					<NcEmojiPicker :close-on-select="true" @select="emoji => icon = emoji">
						<NcButton type="tertiary"
							:aria-label="t('tables', 'Select emoji for table')"
							:title="t('tables', 'Select emoji')"
							@click.prevent>
							{{ icon ? icon : '...' }}
						</NcButton>
					</NcEmojiPicker>
					<input v-model="title"
						:class="{missing: errorTitle}"
						type="text"
						data-cy="editTableTitleInput"
						:placeholder="t('tables', 'Title of the new table')">
				</div>
			</div>
			<div class="row">
				<div class="col-4 space-T mandatory">
					{{ t('tables', 'Description') }}
				</div>
				<div class="col-4">
					<TableDescription v-if="localTable" :description.sync="localTable.description" />
				</div>
			</div>
		</NcAppSettingsSection>
		<NcAppSettingsSection id="column-order" :name="t('tables', 'Column order')">
			<ColumnOrderList
				:columns="tableColumns"
				@update:columnSettings="localColumnSettings = $event" />
		</NcAppSettingsSection>
		<NcAppSettingsSection id="default-sort" :name="t('tables', 'Default sorting')">
			<DefaultSortRules
				:columns="tableColumns"
				:sort-rules="localSortRules"
				@update="localSortRules = $event" />
		</NcAppSettingsSection>
		<NcAppSettingsSection v-if="localTable" id="manage" :name="t('tables', 'Manage')">
			<div class="row">
				<div class="col-4 mandatory">
					{{ t('tables', 'Owner') }}
				</div>
				<div class="col-3 inline space-T-small">
					<NcUserBubble
						:margin="4"
						:size="30"
						:display-name="localTable.ownerDisplayName"
						:user="localTable.owner" />
				</div>
			</div>
			<div class="row">
				<div class="fix-col-4 space-T justify-between">
					<NcButton v-if="!prepareDeleteTable" type="error" data-cy="editTableDeleteBtn" @click="prepareDeleteTable = true">
						{{ t('tables', 'Delete') }}
					</NcButton>
					<NcButton v-if="prepareDeleteTable"
						:wide="true"
						type="error"
						data-cy="editTableConfirmDeleteBtn"
						@click="actionDeleteTable">
						{{ t('tables', 'I really want to delete this table!') }}
					</NcButton>
					<div class="right-additional-button">
						<NcButton v-if="ownsTable(localTable)" @click="actionTransfer">
							{{ t('tables', 'Change owner') }}
						</NcButton>
						<NcButton type="primary" data-cy="editTableSaveBtn" @click="submit">
							{{ t('tables', 'Save') }}
						</NcButton>
					</div>
				</div>
			</div>
		</NcAppSettingsSection>
	</NcAppSettingsDialog>
</template>

<script>
import { NcAppSettingsDialog, NcAppSettingsSection, NcEmojiPicker, NcButton, NcUserBubble } from '@nextcloud/vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import { mapState, mapActions } from 'pinia'
import permissionsMixin from '../../shared/components/ncTable/mixins/permissionsMixin.js'
import { emit } from '@nextcloud/event-bus'
import TableDescription from '../main/sections/TableDescription.vue'
import ColumnOrderList from '../main/partials/editTablePartials/ColumnOrderList.vue'
import DefaultSortRules from '../main/partials/editTablePartials/DefaultSortRules.vue'
import { useTablesStore } from '../../store/store.js'
import { useDataStore } from '../../store/data.js'

export default {
	name: 'EditTable',
	components: {
		NcAppSettingsDialog,
		NcAppSettingsSection,
		NcEmojiPicker,
		NcButton,
		NcUserBubble,
		TableDescription,
		ColumnOrderList,
		DefaultSortRules,
	},
	mixins: [permissionsMixin],
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		tableId: {
			type: Number,
			default: null,
		},
	},
	data() {
		return {
			open: false,
			title: '',
			icon: '',
			errorTitle: false,
			prepareDeleteTable: false,
			localColumnSettings: [],
			tableColumns: [],
			localSortRules: [],
		}
	},
	computed: {
		...mapState(useTablesStore, ['getTable', 'activeTable']),
		localTable() {
			return this.getTable(this.tableId)
		},
	},
	watch: {
		title() {
			if (this.title && this.title.length >= 200) {
				showError(t('tables', 'The title character limit is 200 characters. Please use a shorter title.'))
				this.title = this.title.slice(0, 199)
			}
		},
		async showModal(value) {
			if (value) {
				const table = this.getTable(this.tableId)
				this.title = table.title
				this.icon = table.emoji
				this.tableColumns = await this.getColumnsFromBE({ tableId: this.tableId })
				this.localColumnSettings = table.columnOrder ?? []
				this.localSortRules = table.sort ?? []
				this.open = true
			}
		},
		open(value) {
			if (!value) {
				this.reset()
				this.$emit('close')
			}
		},
	},
	methods: {
		...mapActions(useTablesStore, ['removeTable', 'updateTable']),
		...mapActions(useDataStore, ['getColumnsFromBE']),
		actionCancel() {
			this.open = false
		},
		async submit() {
			if (this.title === '') {
				showError(t('tables', 'Cannot update table. Title is missing.'))
				this.errorTitle = true
			} else {
				const res = await this.updateTable({ id: this.tableId, data: { title: this.title, emoji: this.icon, description: this.localTable.description, columnSettings: this.localColumnSettings, sort: this.localSortRules } })
				if (res) {
					showSuccess(t('tables', 'Updated table "{emoji}{table}".', { emoji: this.icon ? this.icon + ' ' : '', table: this.title }))
					this.actionCancel()
				}
			}
		},
		reset() {
			this.title = ''
			this.errorTitle = false
			this.templateChoice = 'custom'
			this.icon = ''
			this.prepareDeleteTable = false
			this.localColumnSettings = []
			this.tableColumns = []
			this.localSortRules = []
		},
		async actionDeleteTable() {
			const deleteId = this.tableId
			const activeTableId = this.activeTable.id
			this.prepareDeleteTable = false
			const res = await this.removeTable({ tableId: this.tableId })
			if (res) {
				showSuccess(t('tables', 'Table "{emoji}{table}" removed.', { emoji: this.icon ? this.icon + ' ' : '', table: this.title }))

				// if the actual table was deleted, go to startpage
				if (deleteId === activeTableId) {
					await this.$router.push('/').catch(err => err)
				}

				this.actionCancel()
			}

		},
		actionTransfer() {
			const table = this.localTable
			this.open = false
			this.$nextTick(() => emit('tables:table:transfer', table))
		},
	},
}
</script>
<style lang="scss" scoped>

.right-additional-button {
	display: inline-flex;
}

.right-additional-button > button {
	margin-inline-start: calc(var(--default-grid-baseline) * 3);
}

:deep(.element-description) {
	padding-inline: 0 !important;
	max-width: 100%;
}

.content-emoji {
	display: inline-flex;
	align-items: center;
}

</style>
