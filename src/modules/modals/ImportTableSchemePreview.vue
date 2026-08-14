<!--
	- SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
	- SPDX-License-Identifier: AGPL-3.0-or-late
-->
<template>
	<div class="import-table-scheme-preview">
		<div class="preview-section">
			<div class="preview-section__heading">
				<h4 class="preview-section__subtitle">
					{{ t('tables', 'New Columns') }}
				</h4>
			</div>
			<div v-if="addColumnsConfig.length" class="preview-option__value">
				{{ addColumnsConfig.length }} {{ t('tables', 'columns') }}
			</div>
			<div v-if="addColumnsConfig.length" class="preview-list">
				<div v-for="(columnEntry, index) in addColumnsConfig" :key="columnEntry.key" class="preview-card">
					<div class="preview-card__header">
						<NcCheckboxRadioSwitch v-model="addColumnsConfig[index].included" class="preview-card__choice" type="switch">
							{{ columnEntry.title }}
						</NcCheckboxRadioSwitch>
					</div>
					<ul v-if="columnEntry.included" class="preview-card__meta">
						<li>
							{{ t('tables', 'UUID') }}: {{ columnEntry.column.uuid }}
						</li>
						<li>
							{{ t('tables', 'Type') }}: {{ columnEntry.column.type }}
						</li>
						<li>
							{{ t('tables', 'Description') }}: {{ columnEntry.shortDescription }}
						</li>
						<li>
							{{ t('tables', 'Technical name') }}: {{ columnEntry.column.technicalName }}
						</li>
					</ul>
				</div>
			</div>
			<div v-else class="preview-empty">
				{{ t('tables', 'No columns to add') }}
			</div>
		</div>

		<div class="preview-section">
			<div class="preview-section__heading">
				<h4 class="preview-section__subtitle">
					{{ t('tables', 'Change Columns') }}
				</h4>
			</div>
			<div v-if="modifyColumnsConfig.length" class="preview-option__value">
				{{ modifyColumnsConfig.length }} {{ t('tables', 'columns') }}
			</div>
			<div v-if="modifyColumnsConfig.length" class="preview-list">
				<div v-for="(columnEntry, index) in modifyColumnsConfig" :key="columnEntry.key" class="preview-card">
					<div class="preview-card__header">
						<NcCheckboxRadioSwitch v-model="modifyColumnsConfig[index].included" class="preview-card__choice" type="switch">
							{{ columnEntry.title }}
						</NcCheckboxRadioSwitch>
					</div>
					<ul v-if="columnEntry.included" class="preview-card__meta">
						<li>
							{{ t('tables', 'UUID') }}: {{ columnEntry.column.to.uuid || '' }}
						</li>
						<li>
							{{ t('tables', 'From') }}: {{ columnEntry.column.from.title || '' }}
						</li>
						<li>
							{{ t('tables', 'To') }}: {{ columnEntry.column.to.title || '' }}
						</li>
						<li>
							{{ t('tables', 'Technical name') }}: {{ columnEntry.column.to.technicalName || '' }}
						</li>
					</ul>
				</div>
			</div>
			<div v-else class="preview-empty">
				{{ t('tables', 'No columns to change') }}
			</div>
		</div>

		<div class="preview-section">
			<div class="preview-section__heading">
				<h4 class="preview-section__subtitle">
					{{ t('tables', 'Remove Columns') }}
				</h4>
			</div>
			<div v-if="removeColumnsConfig.length" class="preview-option__value">
				{{ removeColumnsConfig.length }} {{ t('tables', 'columns') }}
			</div>
			<div v-if="removeColumnsConfig.length" class="preview-list">
				<div v-for="(columnEntry, index) in removeColumnsConfig" :key="columnEntry.key" class="preview-card">
					<div class="preview-card__header">
						<NcCheckboxRadioSwitch v-model="removeColumnsConfig[index].included" class="preview-card__choice" type="switch">
							{{ columnEntry.title }}
						</NcCheckboxRadioSwitch>
					</div>
					<ul v-if="columnEntry.included" class="preview-card__meta">
						<li>
							{{ t('tables', 'UUID') }}: {{ columnEntry.column.uuid }}
						</li>
						<li>
							{{ t('tables', 'Type') }}: {{ columnEntry.column.type }}
						</li>
						<li>
							{{ t('tables', 'Description') }}: {{ columnEntry.shortDescription }}
						</li>
						<li>
							{{ t('tables', 'Technical name') }}: {{ columnEntry.column.technicalName }}
						</li>
					</ul>
				</div>
			</div>
			<div v-else class="preview-empty">
				{{ t('tables', 'No columns to remove') }}
			</div>
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcCheckboxRadioSwitch } from '@nextcloud/vue'

export default {
	name: 'ImportTableSchemePreview',

	components: {
		NcCheckboxRadioSwitch,
	},

	props: {
		previewData: {
			type: Object,
			default() {
				return {
					title: '',
					emoji: '',
					description: '',
					columns: [],
					views: [],
				}
			},
		},
		table: {
			type: Object,
			default: null,
		},
	},

	emits: ['update'],

	data() {
		return {
			addColumnsConfig: [],
			removeColumnsConfig: [],
			modifyColumnsConfig: [],
		}
	},

	watch: {
		previewData: {
			handler() {
				this.initializeSelection()
			},
			deep: true,
			immediate: true,
		},
		addColumnsConfig: {
			handler() {
				this.emitPayload()
			},
			deep: true,
		},
		removeColumnsConfig: {
			handler() {
				this.emitPayload()
			},
			deep: true,
		},
		modifyColumnsConfig: {
			handler() {
				this.emitPayload()
			},
			deep: true,
		},
	},

	methods: {
		t,

		initializeSelection() {
			const addedColumns = this.previewData?.addedColumns ?? {}
			const removedColumns = this.previewData?.removedColumns ?? {}
			const modifiedColumns = this.previewData?.modifiedColumns ?? {}

			this.addColumnsConfig = this.mapColumnsConfig(addedColumns)
			this.removeColumnsConfig = this.mapColumnsConfig(removedColumns)
			this.modifyColumnsConfig = this.mapModifiedColumnsConfig(modifiedColumns)

			this.emitPayload()
		},

		mapColumnsConfig(columnsMap) {
			return Object.entries(columnsMap).map(([uuid, column], index) => ({
				key: `${uuid || 'column'}-${index}`,
				title: column?.title || t('tables', 'Untitled column'),
				shortDescription: this.getShortDescription(column?.description),
				column,
				included: true,
			}))
		},

		mapModifiedColumnsConfig(columnsMap) {
			return Object.entries(columnsMap).map(([uuid, column], index) => ({
				key: `${uuid || 'column'}-${index}`,
				title: column?.to?.title || column?.from?.title || t('tables', 'Untitled column'),
				column,
				included: true,
			}))
		},

		getShortDescription(description) {
			if (!description) {
				return ''
			}

			return description.length > 50 ? description.substring(0, 50) + '...' : description
		},

		emitPayload() {
			const payload = {
				addColumns: this.addColumnsConfig
					.filter(item => item.included)
					.map(item => ({ ...item.column })),
				removeColumns: this.removeColumnsConfig
					.filter(item => item.included)
					.map(item => ({ ...item.column })),
				modifyColumns: this.modifyColumnsConfig
					.filter(item => item.included)
					.map(item => ({
						from: { ...item.column.from },
						to: { ...item.column.to },
					})),
			}

			this.$emit('update', payload)
		},
	},
}
</script>

<style lang="scss" scoped>
.import-table-scheme-preview {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 3);
}

.preview-section {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
	padding: calc(var(--default-grid-baseline) * 2);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
}

.preview-section--option {
	gap: calc(var(--default-grid-baseline) * 0.5);
}

.preview-section__title,
.preview-section__subtitle {
	margin: 0;
	font-weight: 600;
}

.preview-section__heading {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 2);
	flex-wrap: wrap;
}

.preview-option {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 0.5);
}

.preview-option__label {
	display: inline-flex;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 1.5);
	font-weight: 600;
}

.preview-option__value {
	padding-inline-start: calc(var(--default-grid-baseline) * 3);
	color: var(--color-text-maxcontrast);
	white-space: pre-wrap;
}

.preview-list {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 1.5);
}

.preview-card {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 1);
	padding: calc(var(--default-grid-baseline) * 1.5);
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius);
}

.preview-card__header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: calc(var(--default-grid-baseline) * 2);
}

.preview-card__title {
	font-weight: 600;
}

.preview-card__meta {
	color: var(--color-text-maxcontrast);
	font-size: 0.9rem;
}

.preview-card__actions {
	display: flex;
	gap: calc(var(--default-grid-baseline) * 2);
	flex-wrap: wrap;
}

.preview-card__choice {
	display: inline-flex;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 0.75);
}

.preview-empty {
	color: var(--color-text-maxcontrast);
}
</style>
