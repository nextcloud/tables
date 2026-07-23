<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="row space-R">
		<h3 class="section-heading">
			{{ t('tables', 'Title') }}
		</h3>

		<!-- title -->
		<div class="fix-col-4 mandatory title space-T" :class="{error: titleMissingError}">
			{{ t('tables', 'Title') }}
		</div>
		<div class="fix-col-4" :class="{error: titleMissingError}">
			<input v-model="localTitle" data-cy="columnTypeFormInput" :placeholder="t('tables', 'Enter a column title')">
		</div>

		<!-- description -->
		<div class="fix-col-4 title space-T">
			{{ t('tables', 'Description') }}
		</div>
		<div class="fix-col-4">
			<textarea v-model="localDescription" />
		</div>

		<!-- mandatory -->
		<div class="fix-col-4 title space-T">
			{{ t('tables', 'Mandatory') }}
		</div>
		<div class="fix-col-4">
			<NcCheckboxRadioSwitch v-model="localMandatory" type="switch" />
		</div>

		<!-- add to views -->
		<div v-if="!editColumn && views.length > 0" class="fix-col-4 title space-T">
			{{ t('tables', 'Add column to other views') }}
		</div>
		<div v-if="!editColumn && views.length > 0" class="fix-col-4">
			<NcSelect
				v-model="localSelectedViews"
				:multiple="true"
				:options="viewsForTable"
				:get-option-key="(option) => option.id"
				:placeholder="t('tables', 'Column')"
				label="title"
				:aria-label-combobox="t('tables', 'Column')">
				<template #option="props">
					<div>
						{{ props.emoji }}
						{{ props.title }}
					</div>
				</template>
				<template #selected-option="props">
					<div>
						{{ props.emoji }}
						{{ props.title }}
					</div>
				</template>
			</NcSelect>
		</div>

		<h3 class="section-heading">
			{{ t('tables', 'Advanced settings') }}
		</h3>

		<div class="fix-col-4">
			<NcButton
				type="tertiary"
				data-cy="columnAdvancedSettingsToggle"
				:aria-expanded="showAdvanced"
				:aria-label="advancedToggleLabel"
				@click="showAdvanced = !showAdvanced">
				<template #icon>
					<ChevronUp v-if="showAdvanced" :size="20" />
					<ChevronDown v-else :size="20" />
				</template>
				{{ advancedToggleLabel }}
			</NcButton>
		</div>

		<template v-if="showAdvanced">
			<!-- technical name -->
			<div class="fix-col-4 title space-T" :class="{error: technicalNameInvalidError}">
				{{ t('tables', 'Technical name') }}
			</div>
			<div class="fix-col-4" :class="{error: technicalNameInvalidError}">
				<input
					v-model="localTechnicalName"
					type="text"
					data-cy="columnTechnicalNameInput"
					:placeholder="t('tables', 'Optional, e.g. customer_name')">
			</div>

			<!-- warning for technical name changes -->
			<div class="fix-col-4 space-T">
				<NcNoteCard type="warning">
					<p>{{ t('tables', 'Changing the technical name affects integrations and API. Make sure to update your services accordingly.') }}</p>
				</NcNoteCard>
			</div>

			<!-- column width -->
			<div class="fix-col-4 mandatory title space-T" :class="{error: widthInvalidError}">
				{{ t('tables', 'Column width') }}
			</div>
			<div class="fix-col-4" :class="{error: widthInvalidError}">
				<input
					v-model.number="localColumnWidth"
					type="number"
					pattern="\d+"
					:min="COLUMN_WIDTH_MIN"
					:max="COLUMN_WIDTH_MAX"
					:placeholder="t('tables', 'Enter a column width between {min} and {max}', { min: COLUMN_WIDTH_MIN, max: COLUMN_WIDTH_MAX })">
			</div>
		</template>
	</div>
</template>

<script>
import { NcButton, NcCheckboxRadioSwitch, NcNoteCard, NcSelect } from '@nextcloud/vue'
import { mapState } from 'pinia'
import { translate as t } from '@nextcloud/l10n'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import ChevronUp from 'vue-material-design-icons/ChevronUp.vue'
import { useTablesStore } from '../../../../../../store/store.js'
import { COLUMN_WIDTH_MAX, COLUMN_WIDTH_MIN } from '../../../../../constants.js'

export default {
	name: 'MainForm',
	components: {
		ChevronDown,
		ChevronUp,
		NcButton,
		NcCheckboxRadioSwitch,
		NcNoteCard,
		NcSelect,
	},
	props: {
		title: {
			type: String,
			default: null,
		},
		description: {
			type: String,
			default: null,
		},
		mandatory: {
			type: Boolean,
			default: null,
		},
		technicalName: {
			type: String,
			default: null,
		},
		selectedViews: {
			type: Array,
			default: null,
		},
		titleMissingError: {
			type: Boolean,
			default: false,
		},
		widthInvalidError: {
			type: Boolean,
			default: false,
		},
		technicalNameInvalidError: {
			type: Boolean,
			default: false,
		},
		editColumn: {
			type: Boolean,
			default: false,
		},
		customSettings: {
			type: Object,
			default() {
				return {}
			},
		},
	},
	emits: [
		'update:customSettings',
		'update:description',
		'update:mandatory',
		'update:selectedViews',
		'update:technicalName',
		'update:title',
	],
	data() {
		return {
			COLUMN_WIDTH_MIN,
			COLUMN_WIDTH_MAX,
			showAdvanced: false,
		}
	},
	computed: {
		...mapState(useTablesStore, ['views', 'activeElement', 'isView']),
		advancedToggleLabel() {
			return this.showAdvanced
				? t('tables', 'Hide advanced settings')
				: t('tables', 'Show advanced settings')
		},
		localTitle: {
			get() { return this.title },
			set(title) { this.$emit('update:title', title) },
		},
		localDescription: {
			get() { return this.description },
			set(description) { this.$emit('update:description', description) },
		},
		localTechnicalName: {
			get() { return this.technicalName },
			set(technicalName) { this.$emit('update:technicalName', technicalName) },
		},
		localMandatory: {
			get() { return this.mandatory },
			set(mandatory) { this.$emit('update:mandatory', mandatory) },
		},
		localSelectedViews: {
			get() { return this.selectedViews },
			set(selectedViews) {
				this.$emit('update:selectedViews', selectedViews)
			},
		},
		localColumnWidth: {
			get() { return this.customSettings?.width ?? null },
			set(width) {
				this.$emit('update:customSettings', { ...this.customSettings, ...{ width } })
			},
		},
		viewsForTable() {
			if (this.isView) {
				return this.views.filter(view => view.tableId === this.activeElement.tableId && view !== this.activeElement).filter(view => !this.localSelectedViews.includes(view))
			}
			return this.views.filter(view => view.tableId === this.activeElement?.id).filter(view => !this.localSelectedViews.includes(view))
		},
	},
	watch: {
		technicalNameInvalidError: 'expandAdvancedOnError',
		widthInvalidError: 'expandAdvancedOnError',
	},

	mounted() {
		if (this.editColumn) {
			if (this.technicalName || this.customSettings?.width) {
				this.showAdvanced = true
			}
			return
		}
		if (!this.isView) {
			this.localSelectedViews = this.viewsForTable
		} else {
			this.localSelectedViews = []
		}
	},
	methods: {
		t,
		expandAdvancedOnError(show) {
			if (show) {
				this.showAdvanced = true
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.error {
	color:  var(--color-error);
}

.error input {
	border-color: var(--color-error);
}

.section-heading {
	margin: calc(var(--default-grid-baseline) * 2) 0 var(--default-grid-baseline) 0;
	font-size: 20px;
	font-weight: 600;
	width: 100%;
}
</style>
