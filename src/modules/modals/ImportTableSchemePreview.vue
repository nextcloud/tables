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
				<div v-if="importTitle" class="preview-card__meta preview-card__meta--compare">
					<ul class="preview-card__meta--before">
						<li class="preview-card__meta-title">
							{{ t('tables', 'From') }}:
						</li>
						<li>
							{{ previewData.title.from || '' }}
						</li>
					</ul>
					<ul class="preview-card__meta--after">
						<li class="preview-card__meta-title">
							{{ t('tables', 'To') }}:
						</li>
						<li>
							{{ previewData.title.to || '' }}
						</li>
					</ul>
				</div>
			</div>
		</div>

		<div class="preview-section preview-section--option">
			<div class="preview-option">
				<NcCheckboxRadioSwitch v-model="importEmoji" class="preview-option__label" type="switch">
					{{ t('tables', 'Emoji') }}
				</NcCheckboxRadioSwitch>
				<div v-if="importTitle" class="preview-card__meta preview-card__meta--compare">
					<ul class="preview-card__meta--before">
						<li class="preview-card__meta-title">
							{{ t('tables', 'From') }}:
						</li>
						<li>
							{{ previewData.emoji.from || '' }}
						</li>
					</ul>
					<ul class="preview-card__meta--after">
						<li class="preview-card__meta-title">
							{{ t('tables', 'To') }}:
						</li>
						<li>
							{{ previewData.emoji.to || '' }}
						</li>
					</ul>
				</div>
			</div>
		</div>

		<div class="preview-section preview-section--option">
			<div class="preview-option">
				<NcCheckboxRadioSwitch v-model="importDescription" class="preview-option__label" type="switch">
					{{ t('tables', 'Description') }}
				</NcCheckboxRadioSwitch>
				<div v-if="importTitle" class="preview-card__meta preview-card__meta--compare">
					<ul class="preview-card__meta--before">
						<li class="preview-card__meta-title">
							{{ t('tables', 'From') }}:
						</li>
						<li>
							{{ getShortDescription(previewData.description.from || '') }}
						</li>
					</ul>
					<ul class="preview-card__meta--after">
						<li class="preview-card__meta-title">
							{{ t('tables', 'To') }}:
						</li>
						<li>
							{{ getShortDescription(previewData.description.to || '') }}
						</li>
					</ul>
				</div>
			</div>
		</div>

		<!-- Columns Section -->
		<div class="preview-section">
			<div class="preview-section__heading">
				<h4 class="preview-section__subtitle">
					{{ t('tables', 'New Columns') }}
				</h4>
			</div>
			<div v-if="addColumnsConfig.length" class="preview-list">
				<div v-for="(columnEntry, index) in addColumnsConfig" :key="columnEntry.key" class="preview-card">
					<div class="preview-card__header">
						<NcCheckboxRadioSwitch v-model="addColumnsConfig[index].included" class="preview-card__choice" type="switch">
							{{ columnEntry.uuid }}
						</NcCheckboxRadioSwitch>
					</div>
					<ul v-if="columnEntry.included" class="preview-card__meta">
						<li>
							{{ t('tables', 'Title') }}: {{ columnEntry.column.title }}
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
			<div v-if="modifyColumnsConfig.length" class="preview-list">
				<div v-for="(columnEntry, index) in modifyColumnsConfig" :key="columnEntry.key" class="preview-card">
					<div class="preview-card__header">
						<NcCheckboxRadioSwitch v-model="modifyColumnsConfig[index].included" class="preview-card__choice" type="switch">
							{{ columnEntry.uuid }}
						</NcCheckboxRadioSwitch>
					</div>
					<div v-if="columnEntry.included" class="preview-card__meta preview-card__meta--compare">
						<ul class="preview-card__meta--before">
							<li class="preview-card__meta-title">
								{{ t('tables', 'From') }}:
							</li>
							<li>
								{{ t('tables', 'Title') }}: {{ columnEntry.column.from.title || '' }}
							</li>
							<li>
								{{ t('tables', 'Type') }}: {{ columnEntry.column.from.type || '' }}
							</li>
							<li>
								{{ t('tables', 'Description') }}: {{ getShortDescription(columnEntry.column.from.description || '') }}
							</li>
							<li>
								{{ t('tables', 'Technical name') }}: {{ columnEntry.column.from.technicalName || '' }}
							</li>
						</ul>
						<ul class="preview-card__meta--after">
							<li class="preview-card__meta-title">
								{{ t('tables', 'To') }}:
							</li>
							<li>
								{{ t('tables', 'Title') }}: {{ columnEntry.column.to.title || '' }}
							</li>
							<li>
								{{ t('tables', 'Type') }}: {{ columnEntry.column.to.type || '' }}
							</li>
							<li>
								{{ t('tables', 'Description') }}: {{ getShortDescription(columnEntry.column.to.description || '') }}
							</li>
							<li>
								{{ t('tables', 'Technical name') }}: {{ columnEntry.column.to.technicalName || '' }}
							</li>
						</ul>
					</div>
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
			<div v-if="removeColumnsConfig.length" class="preview-list">
				<div v-for="(columnEntry, index) in removeColumnsConfig" :key="columnEntry.key" class="preview-card">
					<div class="preview-card__header">
						<NcCheckboxRadioSwitch v-model="removeColumnsConfig[index].included" class="preview-card__choice" type="switch">
							{{ columnEntry.uuid }}
						</NcCheckboxRadioSwitch>
					</div>
					<ul v-if="columnEntry.included" class="preview-card__meta">
						<li>
							{{ t('tables', 'Title') }}: {{ columnEntry.column.title }}
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

		<!-- Views Section -->
		<div class="preview-section">
			<div class="preview-section__heading">
				<h4 class="preview-section__subtitle">
					{{ t('tables', 'New Views') }}
				</h4>
			</div>
			<div v-if="addViewsConfig.length" class="preview-option__value">
				{{ addViewsConfig.length }} {{ t('tables', 'views') }}
			</div>
			<div v-if="addViewsConfig.length" class="preview-list">
				<div v-for="(viewEntry, index) in addViewsConfig" :key="viewEntry.key" class="preview-card">
					<div class="preview-card__header">
						<NcCheckboxRadioSwitch v-model="addViewsConfig[index].included" class="preview-card__choice" type="switch">
							{{ viewEntry.uuid }}
						</NcCheckboxRadioSwitch>
					</div>
					<ul v-if="viewEntry.included" class="preview-card__meta">
						<li>
							{{ t('tables', 'Title') }}: {{ viewEntry.view.title }}
						</li>
						<li>
							{{ t('tables', 'Description') }}: {{ viewEntry.shortDescription }}
						</li>
						<li>
							{{ t('tables', 'Technical name') }}: {{ viewEntry.view.technicalName }}
						</li>
					</ul>
				</div>
			</div>
			<div v-else class="preview-empty">
				{{ t('tables', 'No views to add') }}
			</div>
		</div>

		<div class="preview-section">
			<div class="preview-section__heading">
				<h4 class="preview-section__subtitle">
					{{ t('tables', 'Change Views') }}
				</h4>
			</div>
			<div v-if="modifyViewsConfig.length" class="preview-list">
				<div v-for="(viewEntry, index) in modifyViewsConfig" :key="viewEntry.key" class="preview-card">
					<div class="preview-card__header">
						<NcCheckboxRadioSwitch v-model="modifyViewsConfig[index].included" class="preview-card__choice" type="switch">
							{{ viewEntry.uuid }}
						</NcCheckboxRadioSwitch>
					</div>
					<div v-if="viewEntry.included" class="preview-card__meta preview-card__meta--compare">
						<ul class="preview-card__meta--before">
							<li class="preview-card__meta-title">
								{{ t('tables', 'From') }}:
							</li>
							<li>
								{{ t('tables', 'Title') }}: {{ viewEntry.view.from.title || '' }}
							</li>
							<li>
								{{ t('tables', 'Description') }}: {{ getShortDescription(viewEntry.view.from.description || '') }}
							</li>
							<li>
								{{ t('tables', 'Technical name') }}: {{ viewEntry.view.from.technicalName || '' }}
							</li>
						</ul>
						<ul class="preview-card__meta--after">
							<li class="preview-card__meta-title">
								{{ t('tables', 'To') }}:
							</li>
							<li>
								{{ t('tables', 'Title') }}: {{ viewEntry.view.to.title || '' }}
							</li>
							<li>
								{{ t('tables', 'Description') }}: {{ getShortDescription(viewEntry.view.to.description || '') }}
							</li>
							<li>
								{{ t('tables', 'Technical name') }}: {{ viewEntry.view.to.technicalName || '' }}
							</li>
						</ul>
					</div>
				</div>
			</div>
			<div v-else class="preview-empty">
				{{ t('tables', 'No columns to change') }}
			</div>
		</div>

		<div class="preview-section">
			<div class="preview-section__heading">
				<h4 class="preview-section__subtitle">
					{{ t('tables', 'Remove Views') }}
				</h4>
			</div>
			<div v-if="removeViewsConfig.length" class="preview-list">
				<div v-for="(viewEntry, index) in removeViewsConfig" :key="viewEntry.key" class="preview-card">
					<div class="preview-card__header">
						<NcCheckboxRadioSwitch v-model="removeViewsConfig[index].included" class="preview-card__choice" type="switch">
							{{ viewEntry.uuid }}
						</NcCheckboxRadioSwitch>
					</div>
					<ul v-if="viewEntry.included" class="preview-card__meta">
						<li>
							{{ t('tables', 'Title') }}: {{ viewEntry.view.title }}
						</li>
						<li>
							{{ t('tables', 'Description') }}: {{ viewEntry.shortDescription }}
						</li>
						<li>
							{{ t('tables', 'Technical name') }}: {{ viewEntry.view.technicalName }}
						</li>
					</ul>
				</div>
			</div>
			<div v-else class="preview-empty">
				{{ t('tables', 'No views to remove') }}
			</div>
		</div>

		<!-- Column Order Section -->
		<div class="preview-section preview-section--option">
			<div class="preview-option">
				<NcCheckboxRadioSwitch v-model="importColumnOrder" class="preview-option__label" type="switch">
					{{ t('tables', 'Column Order') }}
				</NcCheckboxRadioSwitch>
				<div v-if="importColumnOrder" class="preview-card__meta preview-card__meta--compare">
					<ul class="preview-card__meta--before">
						<li class="preview-card__meta-title">
							{{ t('tables', 'From') }}:
						</li>
						<li v-for="(column, index) in previewData.columnOrderChanges.from" :key="index">
							{{ column.order }}. {{ column.columnTitle }}
						</li>
					</ul>
					<ul class="preview-card__meta--after">
						<li class="preview-card__meta-title">
							{{ t('tables', 'To') }}:
						</li>
						<li v-for="(column, index) in previewData.columnOrderChanges.to" :key="index">
							{{ column.order }}. {{ column.columnTitle }}
						</li>
					</ul>
				</div>
			</div>
		</div>

		<!-- Sort Section -->
		<div class="preview-section preview-section--option">
			<div class="preview-option">
				<NcCheckboxRadioSwitch v-model="importSort" class="preview-option__label" type="switch">
					{{ t('tables', 'Sort') }}
				</NcCheckboxRadioSwitch>
				<div v-if="importSort" class="preview-card__meta preview-card__meta--compare">
					<ul class="preview-card__meta--before">
						<li class="preview-card__meta-title">
							{{ t('tables', 'From') }}:
						</li>
						<li v-for="(sort, index) in previewData.sortChanges.from" :key="index">
							{{ sort.columnTitle }} ({{ sort.mode }})
						</li>
					</ul>
					<ul class="preview-card__meta--after">
						<li class="preview-card__meta-title">
							{{ t('tables', 'To') }}:
						</li>
						<li v-for="(sort, index) in previewData.sortChanges.to" :key="index">
							{{ sort.columnTitle }} ({{ sort.mode }})
						</li>
					</ul>
				</div>
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
					title: {
						from: '',
						to: '',
					},
					emoji: {
						from: '',
						to: '',
					},
					description: {
						from: '',
						to: '',
					},
					columns: {
						addColumns: [],
						removeColumns: [],
						modifyColumns: [],
					},
					views: {
						addViews: [],
						removeViews: [],
						modifyViews: [],
					},
					columnOrderChanges: {
						from: [],
						to: [],
					},
					sortChanges: {
						from: [],
						to: [],
					},
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
			importColumnOrder: true,
			importSort: true,
			addColumnsConfig: [],
			removeColumnsConfig: [],
			modifyColumnsConfig: [],
			addViewsConfig: [],
			removeViewsConfig: [],
			modifyViewsConfig: [],
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
		importTitle: {
			handler() {
				this.emitPayload()
			},
		},
		importEmoji: {
			handler() {
				this.emitPayload()
			}
		},
		importDescription: {
			handler() {
				this.emitPayload()
			}
		},
		importColumnOrder: {
			handler() {
				this.emitPayload()
			}
		},
		importSort: {
			handler() {
				this.emitPayload()
			}
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
		addViewsConfig: {
			handler() {
				this.emitPayload()
			},
			deep: true,
		},
		removeViewsConfig: {
			handler() {
				this.emitPayload()
			},
			deep: true,
		},
		modifyViewsConfig: {
			handler() {
				this.emitPayload()
			},
			deep: true,
		},
	},

	methods: {
		t,

		initializeSelection() {
			const addColumns = this.previewData?.columns?.addColumns ?? {}
			const removeColumns = this.previewData?.columns?.removeColumns ?? {}
			const modifyColumns = this.previewData?.columns?.modifyColumns ?? {}

			this.addColumnsConfig = this.mapColumnsConfig(addColumns)
			this.removeColumnsConfig = this.mapColumnsConfig(removeColumns)
			this.modifyColumnsConfig = this.mapModifiedColumnsConfig(modifyColumns)

			const addViews = this.previewData?.views?.addViews ?? {}
			const removeViews = this.previewData?.views?.removeViews ?? {}
			const modifyViews = this.previewData?.views?.modifyViews ?? {}

			this.addViewsConfig = this.mapViewsConfig(addViews)
			this.removeViewsConfig = this.mapViewsConfig(removeViews)
			this.modifyViewsConfig = this.mapModifiedViewsConfig(modifyViews)

			this.emitPayload()
		},

		mapColumnsConfig(columnsMap) {
			return Object.entries(columnsMap).map(([uuid, column], index) => ({
				key: `${uuid || 'column'}-${index}`,
				uuid,
				shortDescription: this.getShortDescription(column?.description),
				column,
				included: true,
			}))
		},

		mapModifiedColumnsConfig(columnsMap) {
			return Object.entries(columnsMap).map(([uuid, column], index) => ({
				key: `${uuid || 'column'}-${index}`,
				uuid,
				column,
				included: true,
			}))
		},

		mapViewsConfig(viewsMap) {
			return Object.entries(viewsMap).map(([uuid, view], index) => ({
				key: `${uuid || 'view'}-${index}`,
				uuid,
				shortDescription: this.getShortDescription(view?.description),
				view,
				included: true,
			}))
		},

		mapModifiedViewsConfig(viewsMap) {
			return Object.entries(viewsMap).map(([uuid, view], index) => ({
				key: `${uuid || 'view'}-${index}`,
				uuid,
				shortDescription: this.getShortDescription(view?.description),
				view,
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
				title: this.importTitle ? this.previewData.title.to : this.previewData.title.from,
				emoji: this.importEmoji ? this.previewData.emoji.to: this.previewData.emoji.from,
				description: this.importDescription ? this.previewData.description.to: this.previewData.description.from,
				columns: {
					addColumns: this.addColumnsConfig
							.filter(item => item.included)
							.map(item => ({...item.column})),
					removeColumns: this.removeColumnsConfig
							.filter(item => item.included)
							.map(item => ({...item.column})),
					modifyColumns: this.modifyColumnsConfig
							.filter(item => item.included)
							.map(item => ({
								from: {...item.column.from},
								to: {...item.column.to},
							})),
				},
				views: {
					addViews: this.addViewsConfig
							.filter(item => item.included)
							.map(item => ({...item.view})),
					removeViews: this.removeViewsConfig
							.filter(item => item.included)
							.map(item => ({...item.view})),
					modifyViews: this.modifyViewsConfig
							.filter(item => item.included)
							.map(item => ({
								from: {...item.view.from},
								to: {...item.view.to},
							})),
				},
				columnOrder: this.importColumnOrder ? this.previewData.columnOrderChanges.to : this.previewData.columnOrderChanges.from,
				sort: this.importSort ? this.previewData.sortChanges.to : this.previewData.sortChanges.from,
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

.preview-card__meta-title {
	font-weight: 600;
	padding-left: 0 !important;
}

.preview-card__meta--compare {
	display: flex;
	gap: calc(var(--default-grid-baseline) * 2);
}
.preview-card__meta--before,
.preview-card__meta--after {
	width: 100%;
	padding: calc(var(--default-grid-baseline) * 1.5);
}

.preview-card__meta--before li,
.preview-card__meta--after li {
	padding-left: calc(var(--default-grid-baseline) * 2);
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
