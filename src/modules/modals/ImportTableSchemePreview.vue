<!--
	- SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
	- SPDX-License-Identifier: AGPL-3.0-or-late
-->
<template>
	<div class="import-table-scheme-preview">
		<div class="preview-section preview-section--option">
			<div class="preview-option">
				<NcCheckboxRadioSwitch v-model="importTitle" class="preview-option__label" type="switch">
					{{ t('tables', 'Title') }}
				</NcCheckboxRadioSwitch>
				<div v-if="importTitle" class="preview-option__value">
					{{ previewData?.title || '' }}
				</div>
			</div>
		</div>

		<div class="preview-section preview-section--option">
			<div class="preview-option">
				<NcCheckboxRadioSwitch v-model="importEmoji" class="preview-option__label" type="switch">
					{{ t('tables', 'Emoji') }}
				</NcCheckboxRadioSwitch>
				<div v-if="importEmoji" class="preview-option__value">
					{{ previewData?.emoji || '' }}
				</div>
			</div>
		</div>

		<div class="preview-section preview-section--option">
			<div class="preview-option">
				<NcCheckboxRadioSwitch v-model="importDescription" class="preview-option__label" type="switch">
					{{ t('tables', 'Description') }}
				</NcCheckboxRadioSwitch>
				<div v-if="importDescription" class="preview-option__value">
					{{ previewData?.description || '' }}
				</div>
			</div>
		</div>

		<div class="preview-section">
			<div class="preview-section__heading">
				<h4 class="preview-section__subtitle">
					{{ t('tables', 'Columns') }}
				</h4>
				<NcCheckboxRadioSwitch v-model="importColumns" class="preview-option__label" type="switch">
					{{ t('tables', 'Import columns') }}
				</NcCheckboxRadioSwitch>
			</div>
			<div v-if="importColumns" class="preview-option__value">
				{{ columnsConfig.length }} {{ t('tables', 'columns') }}
			</div>
			<div v-if="importColumns && columnsConfig.length" class="preview-list">
				<div v-for="(columnEntry, index) in columnsConfig" :key="columnEntry.key" class="preview-card">
					<div class="preview-card__header">
						<div class="preview-card__actions">
							<NcCheckboxRadioSwitch v-model="columnsConfig[index].included" class="preview-card__choice" type="switch">
								{{ columnEntry.title }}
							</NcCheckboxRadioSwitch>
						</div>
					</div>
					<div v-if="columnEntry.hasConflict && columnEntry.included" class="preview-card__actions">
						<NcCheckboxRadioSwitch v-model="columnsConfig[index].action" class="preview-card__choice" value="override" :name="'column-action-' + index" type="radio">
							{{ t('tables', 'Override existing') }}
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch v-model="columnsConfig[index].action" class="preview-card__choice" value="create" :name="'column-action-' + index" type="radio">
							{{ t('tables', 'Create new') }}
						</NcCheckboxRadioSwitch>
					</div>
					<ul v-if="columnEntry.included" class="preview-card__meta">
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
			<div v-else-if="importColumns" class="preview-empty">
				{{ t('tables', 'No columns to import') }}
			</div>
		</div>

		<div class="preview-section">
			<div class="preview-section__heading">
				<h4 class="preview-section__subtitle">
					{{ t('tables', 'Views') }}
				</h4>
				<NcCheckboxRadioSwitch v-model="importViews" class="preview-option__label" type="switch">
					{{ t('tables', 'Import views') }}
				</NcCheckboxRadioSwitch>
			</div>
			<div v-if="importViews" class="preview-option__value">
				{{ viewsConfig.length }} {{ t('tables', 'views') }}
			</div>
			<div v-if="importViews && viewsConfig.length" class="preview-list">
				<div v-for="(viewEntry, index) in viewsConfig" :key="viewEntry.key" class="preview-card">
					<div class="preview-card__header">
						<NcCheckboxRadioSwitch v-model="viewsConfig[index].included" class="preview-card__choice" type="switch">
							{{ viewEntry.title }}
						</NcCheckboxRadioSwitch>
					</div>
					<div v-if="viewEntry.hasConflict && viewEntry.included" class="preview-card__actions">
						<NcCheckboxRadioSwitch v-model="viewsConfig[index].action" class="preview-card__choice" value="override" :name="'view-action-' + index" type="radio">
							{{ t('tables', 'Override existing') }}
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch v-model="viewsConfig[index].action" class="preview-card__choice" value="create" :name="'view-action-' + index" type="radio">
							{{ t('tables', 'Create new') }}
						</NcCheckboxRadioSwitch>
					</div>
					<ul v-if="viewEntry.included" class="preview-card__meta">
						<li>
							{{ t('tables', 'Emoji') }}: {{ viewEntry.view.emoji || '' }}
						</li>
						<li>
							{{ t('tables', 'Description') }}: {{ viewEntry.view.description || '' }}
						</li>
						<li>
							{{ t('tables', 'Technical name') }}: {{ viewEntry.view.technicalName || '' }}
						</li>
					</ul>
				</div>
			</div>
			<div v-else-if="importViews" class="preview-empty">
				{{ t('tables', 'No views to import') }}
			</div>
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcCheckboxRadioSwitch } from '@nextcloud/vue'
import { mapState, mapActions } from 'pinia'
import { useTablesStore } from '../../store/store.js'
import { useDataStore } from '../../store/data.js'

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
			importTitle: true,
			importEmoji: true,
			importDescription: true,
			importColumns: true,
			importViews: true,
			columnsConfig: [],
			viewsConfig: [],
			existingColumns: [],
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
		importTitle() {
			this.emitPayload()
		},
		importEmoji() {
			this.emitPayload()
		},
		importDescription() {
			this.emitPayload()
		},
		importColumns() {
			this.emitPayload()
		},
		importViews() {
			this.emitPayload()
		},
		columnsConfig: {
			handler() {
				this.emitPayload()
			},
			deep: true,
		},
		viewsConfig: {
			handler() {
				this.emitPayload()
			},
			deep: true,
		},
	},

	methods: {
		...mapActions(useTablesStore, ['reloadViewsOfTable']),
		...mapActions(useDataStore, ['getColumnsFromBE']),
		t,

		async initializeSelection() {
			this.columnsConfig = []
			this.viewsConfig = []
			this.existingColumns = []

			if (this.table?.id) {
				this.existingColumns = await this.getColumnsFromBE({ tableId: this.table.id }) || []
			}

			const existingViews = this.table.views || []
			const previewColumns = Array.isArray(this.previewData?.columns) ? this.previewData.columns : []
			const previewViews = Array.isArray(this.previewData?.views) ? this.previewData.views : []

			this.columnsConfig = previewColumns.map((column, index) => {
				const hasConflict = this.hasUuidConflict(column.uuid, this.existingColumns)
				return {
					key: `${column.uuid || 'column'}-${index}`,
					title: column.title || t('tables', 'Untitled column'),
					shortDescription: column.description?.length > 50 ? column.description.substring(0, 50) + '...' : column.description,
					column,
					included: true,
					hasConflict,
					action: hasConflict ? 'override' : 'create',
				}
			})

			this.viewsConfig = previewViews.map((view, index) => {
				const hasConflict = this.hasUuidConflict(view.uuid, existingViews)
				return {
					key: `${view.uuid || 'view'}-${index}`,
					title: view.title || t('tables', 'Untitled view'),
					shortDescription: view.description?.length > 50 ? view.description.substring(0, 50) + '...' : view.description,
					view,
					included: true,
					hasConflict,
					action: hasConflict ? 'override' : 'create',
				}
			})

			this.emitPayload()
		},

		hasUuidConflict(uuid, items) {
			return Boolean(uuid && Array.isArray(items) && items.some(item => item.uuid === uuid))
		},

		emitPayload() {
			const payload = {
				...this.previewData,
				title: this.importTitle ? (this.previewData?.title ?? '') : (this.table?.title ?? this.previewData?.title ?? ''),
				emoji: this.importEmoji ? (this.previewData?.emoji ?? null) : (this.table?.emoji ?? this.previewData?.emoji ?? null),
				description: this.importDescription ? (this.previewData?.description ?? '') : (this.table?.description ?? this.previewData?.description ?? ''),
			}

			payload.columns = this.importColumns
				? this.columnsConfig.filter(item => item.included).map(item => this.buildImportedColumn(item))
				: []
			payload.views = this.importViews
				? this.viewsConfig.filter(item => item.included).map(item => this.buildImportedView(item))
				: []

			this.$emit('update', payload)
		},

		buildImportedColumn(item) {
			const column = { ...item.column }
			column.selectionOptions = JSON.stringify(column.selectionOptions)
			column.usergroupDefault = JSON.stringify(column.usergroupDefault)
			column.customSettings = JSON.stringify(column.customSettings)
			if (item.action === 'create' && item.hasConflict) {
				column.uuid = ''
			}
			return column
		},

		buildImportedView(item) {
			const view = { ...item.view }
			if (item.action === 'create' && item.hasConflict) {
				view.uuid = ''
			}
			return view
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
