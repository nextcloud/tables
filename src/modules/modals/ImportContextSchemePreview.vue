<!--
	- SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
	- SPDX-License-Identifier: AGPL-3.0-or-later
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
							{{ tableEntry.table.title }}
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
									{{ column.title }} ({{ column.technicalName }})
								</li>
							</ul>
						</li>
						<li>
							<strong>{{ t('tables', 'Views') }}:</strong>
							<ul>
								<li v-for="(view, viewIndex) in tableEntry.table.views" :key="viewIndex">
									{{ view.emoji }} {{ view.title }} ({{ view.technicalName }})
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
							{{ tableEntry.table.title.to }}
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

		<!-- Resources Section -->
		<div class="preview-section preview-section--option">
			<div class="preview-option">
				<NcCheckboxRadioSwitch v-model="importNodes" class="preview-option__label" type="switch" data-cy="importSchemeNodes">
					{{ t('tables', 'Resources') }}
				</NcCheckboxRadioSwitch>
				<div v-if="importNodes" class="preview-card__meta preview-card__meta--compare">
					<ul class="preview-card__meta--before">
						<li class="preview-card__meta-title">
							{{ t('tables', 'From') }}:
						</li>
						<li v-for="(node, index) in previewData.nodes.from" :key="index">
							{{ node.node_title }}
						</li>
					</ul>
					<ul class="preview-card__meta--after">
						<li class="preview-card__meta-title">
							{{ t('tables', 'To') }}:
						</li>
						<li v-for="(column, index) in previewData.nodes.to" :key="index">
							{{ column.node_title }}
						</li>
					</ul>
				</div>
			</div>
		</div>

		<!-- Menu Section -->
		<div v-if="previewData.menu" class="preview-section preview-section--option">
			<div class="preview-option">
				<NcCheckboxRadioSwitch v-model="importMenu" class="preview-option__label" type="switch" data-cy="importSchemeMenu">
					{{ t('tables', 'Menu') }}
				</NcCheckboxRadioSwitch>
				<div v-if="importMenu" class="preview-card__meta preview-card__meta--compare">
					<ul class="preview-card__meta--before">
						<li class="preview-card__meta-title">
							{{ t('tables', 'From') }}:
						</li>
						<li v-for="entry in menuTree(previewData.menu.from)" :key="entry.index" data-cy="importSchemeMenuFrom">
							<span class="preview-menu__title">{{ menuItemTitle(entry.item) }}</span>
							<span class="preview-menu__target">{{ menuItemTarget(entry.item, previewData.nodes.from) }}</span>
							<ul v-if="entry.children.length > 0" class="preview-menu__children">
								<li v-for="child in entry.children" :key="child.index">
									<span class="preview-menu__title">{{ menuItemTitle(child.item) }}</span>
									<span class="preview-menu__target">{{ menuItemTarget(child.item, previewData.nodes.from) }}</span>
								</li>
							</ul>
						</li>
					</ul>
					<ul class="preview-card__meta--after">
						<li class="preview-card__meta-title">
							{{ t('tables', 'To') }}:
						</li>
						<li v-for="entry in menuTree(menuToImport)" :key="entry.index" data-cy="importSchemeMenuTo">
							<span class="preview-menu__title">{{ menuItemTitle(entry.item) }}</span>
							<span class="preview-menu__target">{{ menuItemTarget(entry.item, importedNodes) }}</span>
							<ul v-if="entry.children.length > 0" class="preview-menu__children">
								<li v-for="child in entry.children" :key="child.index">
									<span class="preview-menu__title">{{ menuItemTitle(child.item) }}</span>
									<span class="preview-menu__target">{{ menuItemTarget(child.item, importedNodes) }}</span>
								</li>
							</ul>
						</li>
					</ul>
				</div>
				<ul v-if="importMenu && menuSkipped.length > 0" class="preview-card__meta" data-cy="importSchemeMenuSkipped">
					<li class="preview-card__meta-title">
						{{ t('tables', 'Skipped, not available here') }}:
					</li>
					<li v-for="(item, index) in menuSkipped" :key="index">
						{{ item.label }}
					</li>
				</ul>
			</div>
		</div>

		<!-- Pages Section -->
		<div v-if="previewData.pages" class="preview-section preview-section--option">
			<div class="preview-option">
				<NcCheckboxRadioSwitch v-model="importPages" class="preview-option__label" type="switch" data-cy="importSchemePages">
					{{ t('tables', 'Pages') }}
				</NcCheckboxRadioSwitch>
				<ul v-if="importPages" class="preview-card__meta" data-cy="importSchemePagesList">
					<li v-for="(page, index) in previewData.pages.summary" :key="index">
						{{ pageLabel(page) }}
					</li>
				</ul>
				<ul v-if="importPages && previewData.pages.skipped.length > 0" class="preview-card__meta" data-cy="importSchemePagesSkipped">
					<li class="preview-card__meta-title">
						{{ t('tables', 'Skipped, not available here') }}:
					</li>
					<li v-for="item in previewData.pages.skipped" :key="item.pageIndex + '-' + item.widgetIndex">
						{{ skippedPageLabel(item) }}
					</li>
				</ul>
			</div>
		</div>
	</div>
</template>

<script>
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import { NcCheckboxRadioSwitch } from '@nextcloud/vue'
import ImportTableSchemePreview from './ImportTableSchemePreview.vue'
import svgHelper from '../../shared/components/ncIconPicker/mixins/svgHelper.js';
import { NODE_TYPE_TABLE, NODE_TYPE_VIEW } from '../../shared/constants.ts'

export default {
	name: 'ImportContextSchemePreview',

	components: {
		ImportTableSchemePreview,
		NcCheckboxRadioSwitch,
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
			importMenu: true,
			importPages: true,
			addTablesConfig: [],
			removeColumnsConfig: [],
			modifyTablesConfig: [],
			addViewsConfig: [],
			removeViewsConfig: [],
			modifyViewsConfig: [],
		}
	},

	computed: {
		/**
		 * The menu the import saves: with the tables and views of the scheme, or with the
		 * current ones of the application when those are not imported.
		 *
		 * @return {{to: Array, skipped: Array}|null}
		 */
		menuPreview() {
			const menu = this.previewData.menu
			if (!menu) {
				return null
			}
			return this.importNodes || !menu.withCurrentNodes ? menu : menu.withCurrentNodes
		},

		importedNodes() {
			return this.importNodes ? this.previewData.nodes.to : this.previewData.nodes.from
		},

		/**
		 * The menu the import saves. An item for a page the import adds points at it by
		 * `{ page }`, so without the pages it is left out, and the groups of the other
		 * items are named by their new position.
		 *
		 * @return {Array<object>}
		 */
		menuToImport() {
			const items = this.menuPreview?.to ?? []
			if (this.importPages || !this.previewData.pages) {
				return items
			}
			const positions = new Map()
			const kept = []
			items.forEach((item, index) => {
				if (item.target?.page === undefined) {
					positions.set(index, kept.length)
					kept.push(item)
				}
			})
			return kept.map(item => (item.parent === null || item.parent === undefined ? item : { ...item, parent: positions.get(item.parent) ?? null }))
		},

		/**
		 * @return {Array<{label: string}>} the menu items the import skips, with the reason
		 */
		menuSkipped() {
			const raw = { escape: false, sanitize: false }
			const skipped = (this.menuPreview?.skipped ?? []).map(item => ({
				label: t('tables', '{item}: {reason}', { item: this.menuItemTitle(item), reason: item.reason }, undefined, raw),
			}))
			if (this.importPages || !this.previewData.pages) {
				return skipped
			}
			return skipped.concat((this.menuPreview?.to ?? [])
				.filter(item => item.target?.page !== undefined)
				.map(item => ({
					label: t('tables', '{item}: its page is not imported', { item: this.menuItemTitle(item) }, undefined, raw),
				})))
		},
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
		importMenu: {
			handler() {
				this.emitPayload()
			},
		},
		importPages: {
			handler() {
				this.emitPayload()
			},
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

		/**
		 * @param {Array} items menu items, each naming its group by the group's position in the list
		 * @return {Array<{item: object, index: number, children: Array}>} the top-level items with the items of each group
		 */
		menuTree(items) {
			const entries = []
			const roots = []
			items.forEach((item, index) => {
				const entry = { item, index, children: [] }
				entries[index] = entry
				const group = item.parent === null || item.parent === undefined ? undefined : entries[item.parent]
				if (group) {
					group.children.push(entry)
				} else {
					roots.push(entry)
				}
			})
			return roots
		},

		/**
		 * @param {object} item a menu item
		 * @return {string} the title the item shows
		 */
		menuItemTitle(item) {
			return item.displayTitle || item.title || t('tables', 'Untitled item')
		},

		/**
		 * @param {object} item a menu item
		 * @param {Array} nodes the tables and views its table or view is one of
		 * @return {string} what the item opens
		 */
		menuItemTarget(item, nodes) {
			const target = item.target
			if (!target) {
				return t('tables', 'Group')
			}
			if (target.url !== undefined) {
				return t('tables', 'Link: {url}', { url: target.url }, undefined, { escape: false })
			}
			if (target.page !== undefined) {
				return t('tables', 'Page')
			}
			const isTable = target.table !== undefined
			const uuid = isTable ? target.table : target.view
			const node = (nodes ?? []).find(node => node.node_uuid === uuid && node.node_type === (isTable ? NODE_TYPE_TABLE : NODE_TYPE_VIEW))
			if (!node?.node_title) {
				return isTable ? t('tables', 'Table') : t('tables', 'View')
			}
			return isTable
				? t('tables', 'Table: {title}', { title: node.node_title }, undefined, { escape: false })
				: t('tables', 'View: {title}', { title: node.node_title }, undefined, { escape: false })
		},

		/**
		 * @param {object} page a page of the import as the preview sums it up
		 * @return {string} whether the page is new or which page it updates, with its widgets
		 */
		pageLabel(page) {
			// Vue escapes the text, so the titles are interpolated as they are
			const raw = { escape: false, sanitize: false }
			let label
			if (page.pageType === 'startpage') {
				label = t('tables', 'Updates the start page')
			} else if (page.action === 'update') {
				label = t('tables', 'Updates {page}', { page: page.currentTitle || page.slug }, undefined, raw)
			} else {
				label = t('tables', 'New page {page}', { page: page.title || page.slug || t('tables', 'Untitled page') }, undefined, raw)
			}
			const widgets = n('tables', '%n widget', '%n widgets', page.widgetCount)
			if (page.unlinkedWidgetCount > 0) {
				return t('tables', '{page}, {widgets}, {unlinked} without their table or view', { page: label, widgets, unlinked: page.unlinkedWidgetCount }, undefined, raw)
			}
			return t('tables', '{page}, {widgets}', { page: label, widgets }, undefined, raw)
		},

		/**
		 * @param {object} item a skipped page, or a skipped widget of a page
		 * @return {string} the page, the widget when it is one, and why it is skipped
		 */
		skippedPageLabel(item) {
			const raw = { escape: false, sanitize: false }
			const page = item.page || t('tables', 'Untitled page')
			if (item.widgetIndex === null) {
				return t('tables', '{page}: {reason}', { page, reason: item.reason }, undefined, raw)
			}
			return t('tables', '{page}: {widget}: {reason}', { page, widget: item.widget || t('tables', 'Untitled widget'), reason: item.reason }, undefined, raw)
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
				iconName: this.importIcon ? this.previewData.icon.to : this.previewData.icon.from,
				description: this.importDescription ? this.previewData.description.to : this.previewData.description.from,
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
			// without a menu the import leaves the menu of the application as it is
			if (this.importMenu && this.menuPreview) {
				payload.menu = this.menuToImport
			}
			// without pages the import leaves the pages of the application as they are
			if (this.importPages && this.previewData.pages) {
				payload.pages = this.previewData.pages.to
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
	padding-inline-start: 0 !important;
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
	padding-inline-start: calc(var(--default-grid-baseline) * 2);
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

.preview-menu__title,
.preview-menu__target {
	display: block;
	overflow-wrap: anywhere;
}

.preview-menu__title {
	color: var(--color-main-text);
}
</style>
