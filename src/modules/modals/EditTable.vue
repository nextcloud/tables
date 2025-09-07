<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="showModal"
		:name="t('tables', 'Edit table')"
		size="normal"
		@closing="actionCancel">
		<div class="modal__content" data-cy="editTableModal">
			<div class="row">
				<div class="col-4 mandatory">
					{{ t('tables', 'Title') }}
				</div>
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
					<TableDescription :description.sync="localTable.description" />
				</div>
			</div>
			<div class="row">
				<div class="col-4 mandatory space-T">
					{{ t('tables', 'Owner') }}
				</div>
				<div class="col-3 inline space-T-small">
					<NcUserBubble
						:margin="4"
						:size="30"
						:display-name="localTable.ownerDisplayName"
						:user="localTable.owner" />
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
			</div>
		</div>
	</NcDialog>
</template>

<script>
import { NcDialog, NcEmojiPicker, NcButton, NcUserBubble } from '@nextcloud/vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import { mapState, mapActions } from 'pinia'
import permissionsMixin from '../../shared/components/ncTable/mixins/permissionsMixin.js'
import { emit } from '@nextcloud/event-bus'
import TableDescription from '../main/sections/TableDescription.vue'
import { useTablesStore } from '../../store/store.js'

export default {
	name: 'EditTable',
	components: {
		NcDialog,
		NcEmojiPicker,
		NcButton,
		NcUserBubble,
		TableDescription,
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
			title: '',
			icon: '',
			errorTitle: false,
			prepareDeleteTable: false,
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
		tableId() {
			if (this.tableId) {
				const table = this.getTable(this.tableId)
				this.title = table.title
				this.icon = table.emoji
			}
		},
	},
	methods: {
		...mapActions(useTablesStore, ['removeTable', 'updateTable']),
		actionCancel() {
			this.reset()
			this.$emit('close')
		},
		async submit() {
			if (this.title === '') {
				showError(t('tables', 'Cannot update table. Title is missing.'))
				this.errorTitle = true
			} else {
				const res = await this.updateTable({ id: this.tableId, data: { title: this.title, emoji: this.icon, description: this.localTable.description } })
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
			emit('tables:table:edit', null)
			emit('tables:table:transfer', this.localTable)
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
