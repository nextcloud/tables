<!--
	- SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
	- SPDX-License-Identifier: AGPL-3.0-or-late
-->
<template>
	<div class="import-table-scheme-preview">
		<div class="preview-section preview-section--option">
			<div class="preview-option">
				<NcCheckboxRadioSwitch v-model="importName" class="preview-option__label" type="switch">
					{{ t('tables', 'Title') }}
				</NcCheckboxRadioSwitch>
				<div v-if="importName" class="preview-card__meta preview-card__meta--compare">
					<ul class="preview-card__meta--before">
						<li class="preview-card__meta-title">
							{{ t('tables', 'From') }}:
						</li>
						<li>
							{{ previewData.name.from || '' }}
						</li>
					</ul>
					<ul class="preview-card__meta--after">
						<li class="preview-card__meta-title">
							{{ t('tables', 'To') }}:
						</li>
						<li>
							{{ previewData.name.to || '' }}
						</li>
					</ul>
				</div>
			</div>
		</div>

		<div class="preview-section preview-section--option">
			<div class="preview-option">
				<NcCheckboxRadioSwitch v-model="importIcon" class="preview-option__label" type="switch">
					{{ t('tables', 'Icon') }}
				</NcCheckboxRadioSwitch>
				<div v-if="importIcon" class="preview-card__meta preview-card__meta--compare">
					<ul class="preview-card__meta--before">
						<li class="preview-card__meta-title">
							{{ t('tables', 'From') }}:
						</li>
						<li>
							{{ previewData.icon.from || '' }}
						</li>
					</ul>
					<ul class="preview-card__meta--after">
						<li class="preview-card__meta-title">
							{{ t('tables', 'To') }}:
						</li>
						<li>
							{{ previewData.icon.to || '' }}
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
				<div v-if="importDescription" class="preview-card__meta preview-card__meta--compare">
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

		<!-- Tables Section -->
		<div class="preview-section">
			<div class="preview-section__heading">
				<h4 class="preview-section__subtitle">
					{{ t('tables', 'New Tables') }}
				</h4>
			</div>
			<div v-if="addTablesConfig.length" class="preview-list">
				<div v-for="(tableEntry, index) in addTablesConfig" :key="tableEntry.key" class="preview-card">
					<div class="preview-card__header">
						<NcCheckboxRadioSwitch v-model="addTablesConfig[index].included" class="preview-card__choice" type="switch">
							{{ tableEntry.uuid }}
						</NcCheckboxRadioSwitch>
					</div>
					<ul v-if="tableEntry.included" class="preview-card__meta">
						<li>
							<strong>{{ t('tables', 'Title') }}:</strong> {{ tableEntry.table.title }}
						</li>
						<li>
							<strong>{{ t('tables', 'Description') }}:</strong> {{ getShortDescription(tableEntry.table.description) }}
						</li>
						<li>
							<strong>{{ t('tables', 'Emoji') }}:</strong> {{ tableEntry.table.emoji }}
						</li>
						<li>
							<strong>{{ t('tables', 'Columns') }}:</strong>
							<ul>
								<li v-for="(column, colIndex) in tableEntry.table.columns" :key="colIndex">
									{{ column.title }} ({{ column.technicalName }} - {{ column.uuid }})
								</li>
							</ul>
						</li>
						<li>
							<strong>{{ t('tables', 'Views') }}:</strong>
							<ul>
								<li v-for="(view, viewIndex) in tableEntry.table.views" :key="viewIndex">
									{{ view.emoji }} {{ view.title }} ({{ view.technicalName }} - {{ view.uuid }})
								</li>
							</ul>
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
					{{ t('tables', 'Change Tables') }}
				</h4>
			</div>
			<div v-if="modifyTablesConfig.length" class="preview-list">
				<div v-for="(tableEntry, index) in modifyTablesConfig" :key="tableEntry.key" class="preview-card">
					<div class="preview-card__header">
						<NcCheckboxRadioSwitch v-model="modifyTablesConfig[index].included" class="preview-card__choice" type="switch">
							{{ tableEntry.uuid }}
						</NcCheckboxRadioSwitch>
					</div>
					<div v-if="tableEntry.included" class="preview-card__meta">
						<ImportTableSchemePreview :preview-data="modifyTablesConfig[index].table" @update="(updatedTable) => { modifyTablesConfig[index].tableSubmitPayload = updatedTable }" />
					</div>
				</div>
			</div>
			<div v-else class="preview-empty">
				{{ t('tables', 'No columns to change') }}
			</div>
		</div>

		<!-- Column Order Section -->
		<div class="preview-section preview-section--option">
			<div class="preview-option">
				<NcCheckboxRadioSwitch v-model="importNodes" class="preview-option__label" type="switch">
					{{ t('tables', 'Resources') }}
				</NcCheckboxRadioSwitch>
				<div v-if="importNodes" class="preview-card__meta preview-card__meta--compare">
					<ul class="preview-card__meta--before">
						<li class="preview-card__meta-title">
							{{ t('tables', 'From') }}:
						</li>
						<li v-for="(node, index) in previewData.nodes.from" :key="index">
							{{ node.node_title }} ({{ node.node_uuid }})
						</li>
					</ul>
					<ul class="preview-card__meta--after">
						<li class="preview-card__meta-title">
							{{ t('tables', 'To') }}:
						</li>
						<li v-for="(column, index) in previewData.nodes.to" :key="index">
							{{ column.node_title }} ({{ column.node_uuid }})
						</li>
					</ul>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcCheckboxRadioSwitch, NcIconSvgWrapper } from '@nextcloud/vue'
import ImportTableSchemePreview from './ImportTableSchemePreview.vue'
import svgHelper from "../../shared/components/ncIconPicker/mixins/svgHelper.js";

export default {
	name: 'ImportContextSchemePreview',

	components: {
		ImportTableSchemePreview,
		NcCheckboxRadioSwitch,
		NcIconSvgWrapper,
	},

	mixins: [svgHelper],

	props: {
		previewData: {
			type: Object,
			default() {
				return {
					name: {
						from: '',
						to: '',
					},
					icon: {
						from: '',
						to: '',
					},
					description: {
						from: '',
						to: '',
					},
					tables: {
						addTables: [],
						modifyTables: [],
					},
					nodes: {
						from: [],
						to: [],
					}
				}
			},
		},
	},

	emits: ['update'],

	data() {
		return {
			importName: true,
			importIcon: true,
			importDescription: true,
			importNodes: true,
			addTablesConfig: [],
			removeColumnsConfig: [],
			modifyTablesConfig: [],
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
		importName: {
			handler() {
				this.emitPayload()
			},
		},
		importIcon: {
			handler() {
				this.emitPayload()
			}
		},
		importDescription: {
			handler() {
				this.emitPayload()
			}
		},
		importNodes: {
			handler() {
				this.emitPayload()
			}
		},
		addTablesConfig: {
			handler() {
				this.emitPayload()
			},
			deep: true,
		},
		modifyTablesConfig: {
			handler() {
				this.emitPayload()
			},
			deep: true,
		},
	},

	computed: {
		iconFrom: {
			async get() {
				return await this.getContextIcon(this.previewData.icon.from)
			}
		},
		iconTo: {
			async get() {
				return await this.getContextIcon(this.previewData.icon.to)
			}
		}
	},

	methods: {
		t,

		initializeSelection() {
			const addTables = this.previewData?.addTables ?? {}
			const modifyTables = this.previewData?.modifyTables ?? {}

			this.addTablesConfig = this.mapTablesConfig(addTables)
			this.modifyTablesConfig = this.mapTablesConfig(modifyTables)

			this.emitPayload()
		},

		mapTablesConfig(tables) {
			return Object.entries(tables).map(([uuid, table], index) => ({
				key: `${uuid || 'column'}-${index}`,
				uuid,
				table,
				tableSubmitPayload: {},
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
				name: this.importName ? this.previewData.name.to : this.previewData.name.from,
				iconName: this.importIcon ? this.previewData.icon.to: this.previewData.icon.from,
				description: this.importDescription ? this.previewData.description.to: this.previewData.description.from,
				tables: {
					addTables: this.addTablesConfig
							.filter(item => item.included)
							.map(item => ({uuid: item.uuid, ...item.table})),
					modifyTables: this.modifyTablesConfig
							.filter(item => item.included)
							.map(item => ({uuid: item.uuid, ...item.tableSubmitPayload})),
				},
				nodes: this.importNodes ? this.previewData.nodes.to : this.previewData.nodes.from,
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
