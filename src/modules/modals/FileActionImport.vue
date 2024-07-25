<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="!importResults"
		:name="t('tables', 'Import file into Tables')"
		size="normal"
		@closing="closeImportDialog">
		<div class="modal__content">
			<RowFormWrapper
				class="row"
				:title="t('tables', 'Import as new table')"
				:description="t('tables', 'This will create a new table from the data in this file.')">
				<div style="display: flex; flex-flow: row wrap; align-items: center;">
					<NcCheckboxRadioSwitch
						data-cy="importAsNewTableSwitch"
						:aria-label="t('tables', 'Import as new table')"
						:checked.sync="importAsNew"
						type="switch"
						class="switch"
						style="flex-grow: 0;">
						{{ t('tables', 'Import as new table') }}
					</NcCheckboxRadioSwitch>

					<div style="display: flex; flex-flow: row nowrap; flex-grow: 1;">
						<NcEmojiPicker :close-on-select="true" @select="selectIcon">
							<NcButton
								:aria-label="t('tables', 'Select emoji for table')"
								type="tertiary"
								:disabled="!importAsNew"
								@click.prevent>
								<template #icon>
									{{ newTable.emoji }}
								</template>
							</NcButton>
						</NcEmojiPicker>

						<input
							:aria-label="t('tables', 'Title of the new table')"
							style="flex-grow: 1;"
							:placeholder="newTable.title"
							:disabled="!importAsNew"
							@input="setNewTableTitle">
					</div>
				</div>
			</RowFormWrapper>

			<RowFormWrapper
				class="row"
				:title="t('tables', 'Import into existing table')"
				:description="t('tables', 'This will import the data from this file into an already existing table.')">
				<div style="display: flex; flex-flow: row nowrap; margin: 20px 0;">
					<NcSelect
						v-model="selectedTable"
						data-cy="selectExistingTableDropdown"
						:options="tableOptions"
						:aria-label-combobox="t('tables', 'Select the table to import into')"
						:placeholder="t('tables', 'Select an existing table')"
						:disabled="importAsNew"
						:loading="loadingTables"
						style="flex-grow: 1;" />
				</div>
			</RowFormWrapper>

			<NcCheckboxRadioSwitch
				:aria-label="t('tables', 'Create missing columns')"
				type="switch"
				:disabled="importAsNew"
				:checked.sync="createMissingColumns"
				style="flex-grow: 0;">
				{{ t('tables', 'Create missing columns') }}
			</NcCheckboxRadioSwitch>

			<div class="end">
				<NcButton
					data-cy="fileActionImportButton"
					:aria-label="t('tables', 'Import')"
					type="primary"
					:disabled="importingFile"
					@click="importFile">
					<template v-if="importingFile" #icon>
						<NcLoadingIcon :size="20" />
					</template>

					{{ t('tables', 'Import') }}
				</NcButton>
			</div>
		</div>
	</NcDialog>

	<NcDialog v-else
		:name="t('tables', 'Import successful')"
		:open.sync="showResultsDialog"
		size="small">
		<template #actions>
			<NcButton :aria-label="t('tables', 'Close')" @click="closeResultsDialog()">
				{{ t('tables', 'Close') }}
			</NcButton>
		</template>

		<ImportResults :results="importResults" />
	</NcDialog>
</template>

<script>
import {
	NcDialog,
	NcButton,
	NcSelect,
	NcCheckboxRadioSwitch,
	NcEmojiPicker,
	NcLoadingIcon,
} from '@nextcloud/vue'

import { generateUrl } from '@nextcloud/router'
import { Node } from '@nextcloud/files'
import { showError } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import RowFormWrapper from '../../shared/components/ncTable/partials/rowTypePartials/RowFormWrapper.vue'
import ImportResults from './ImportResults.vue'

export default {
	name: 'FileActionImport',

	components: {
		NcButton,
		NcDialog,
		ImportResults,
		NcLoadingIcon,
		NcSelect,
		NcCheckboxRadioSwitch,
		NcEmojiPicker,
		RowFormWrapper,
	},

	props: {
		file: {
			type: Node,
			default: null,
		},
	},

	data() {
		return {
			importAsNew: true,
			createMissingColumns: true,

			loadingTables: false,
			importingFile: false,

			newTable: {
				emoji: '🔧',
				title: this.file.basename,
			},
			existingTables: [],
			selectedTable: null,

			importResults: null,
			showResultsDialog: true,
		}
	},

	computed: {
		tableOptions() {
			const options = this.existingTables.map(table => {
				return {
					label: `${table.emoji} ${table.title}`,
					value: table.id,
				}
			})

			return options ?? 'No existing tables'
		},
	},

	async mounted() {
		this.loadingTables = true

		const res = await axios.get(generateUrl('/apps/tables/table'))

		if (res.data) {
			res.data.forEach(table => this.existingTables.push({
				title: table.title,
				emoji: table.emoji,
				id: table.id,
			}))
		}

		this.loadingTables = false
	},

	methods: {
		t,
		selectIcon(icon) {
			this.newTable.emoji = icon
		},
		setNewTableTitle(title) {
			this.newTable.title = title.srcElement.value
		},
		closeResultsDialog() {
			this.showResultsDialog = false
		},
		async importFile() {
			this.importingFile = true

			if (this.importAsNew) {
				this.importResults = await importToNewTable(this.newTable.title, this.newTable.emoji, this.file)

				if (!this.importResults) {
					showError(t('tables', 'Could not create table'))
				}
			} else {
				if (!this.selectedTable) {
					showError(t('tables', 'You must select an existing table'))
					return
				}

				this.importResults = await importToExistingTable(this.selectedTable.value, this.file, this.createMissingColumns)

				if (!this.importResults) {
					showError(t('tables', 'Could not import data to table'))
				}
			}

			this.importingFile = false
		},
		closeImportDialog() {
			this.$emit('close')
		},
	},
}

async function importToNewTable(title, emoji, file) {
	const newTable = await insertTable(title, emoji)

	if (!newTable) {
		return false
	}

	return await updateTable(newTable.id, file.path, true)
}

async function importToExistingTable(tableId, file, createMissingColumns) {
	return await updateTable(tableId, file.path, createMissingColumns)
}

async function insertTable(title, emoji) {
	const res = await axios.post(generateUrl('/apps/tables/table'), {
		title,
		emoji,
		template: 'custom',
	})

	return res.data
}

async function updateTable(tableId, path, createMissingColumns) {
	const res = await axios.post(generateUrl(`/apps/tables/import/table/${tableId}`), {
		path,
		createMissingColumns,
	})

	return res.data
}
</script>

<style scoped lang="scss">
.modal__content {
	margin: 20px;
}

.row {
	margin: 20px 0;
}

.switch {
	margin: 5px;
}

.end {
	display: flex;
	flex-flow: row nowrap;
	justify-content: flex-end;
}
</style>
