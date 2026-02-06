<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div style="width: 100%">
		<div class="row space-T">
			<div class="fix-col-4">
				{{ t('tables', 'Default') }}
			</div>
			<div class="fix-col-4 space-L-small">
				<NcCheckboxRadioSwitch type="switch" :checked.sync="mutableColumn.selectionDefault" data-cy="selectionCheckFormDefaultSwitch" />
			</div>
		</div>
		<div class="row space-T">
			<div class="fix-col-4">
				{{ t('tables', 'Row highlight color') }}
			</div>
			<div class="fix-col-4 space-L-small highlight-color-picker">
				<NcColorPicker v-model="highlightColor" :palette="colorPalette">
					<NcButton type="secondary" :style="colorButtonStyle" :aria-label="t('tables', 'Pick a color')">
						<template #icon>
							<FormatColorFill :size="20" />
						</template>
						{{ highlightColor ? '' : t('tables', 'None') }}
					</NcButton>
				</NcColorPicker>
				<NcButton v-if="highlightColor" type="tertiary" :aria-label="t('tables', 'Clear color')" @click="clearHighlightColor">
					<template #icon>
						<Close :size="20" />
					</template>
				</NcButton>
			</div>
		</div>
	</div>
</template>
<script>
import { NcCheckboxRadioSwitch, NcColorPicker, NcButton } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import FormatColorFill from 'vue-material-design-icons/FormatColorFill.vue'
import Close from 'vue-material-design-icons/Close.vue'
import Vue from 'vue'

export default {
	name: 'SelectionCheckForm',
	components: {
		NcCheckboxRadioSwitch,
		NcColorPicker,
		NcButton,
		FormatColorFill,
		Close,
	},
	props: {
		column: {
			type: Object,
			default: null,
		},
		canSave: {
			type: Boolean,
			default: true,
		},
	},
	data() {
		return {
			mutableColumn: this.column,
			highlightColor: this.column?.customSettings?.rowHighlightColor || '',
			colorPalette: [
				'#4caf50', '#8bc34a', '#cddc39', '#ffeb3b',
				'#ffc107', '#ff9800', '#f44336', '#e91e63',
				'#9c27b0', '#2196f3', '#00bcd4', '#009688',
			],
		}
	},
	computed: {
		colorButtonStyle() {
			if (this.highlightColor) {
				return {
					backgroundColor: this.highlightColor,
					borderColor: this.highlightColor,
					color: this.getContrastColor(this.highlightColor),
				}
			}
			return {}
		},
	},
	watch: {
		highlightColor(newColor) {
			console.debug('highlightColor changed to:', newColor)
			if (!this.mutableColumn.customSettings) {
				Vue.set(this.mutableColumn, 'customSettings', {})
			}
			Vue.set(this.mutableColumn.customSettings, 'rowHighlightColor', newColor)
		},
		column() {
			this.mutableColumn = this.column
			this.highlightColor = this.column?.customSettings?.rowHighlightColor || ''
		},
	},
	created() {
		if (this.mutableColumn.selectionDefault === 'true') {
			this.mutableColumn.selectionDefault = true
			return
		}
		if (typeof this.mutableColumn.selectionDefault !== 'boolean') {
			this.mutableColumn.selectionDefault = false
		}
		if (!this.mutableColumn.customSettings) {
			Vue.set(this.mutableColumn, 'customSettings', {})
		}
	},
	methods: {
		t,
		clearHighlightColor() {
			this.highlightColor = ''
		},
		getContrastColor(hexcolor) {
			const hex = hexcolor.replace('#', '')
			const r = parseInt(hex.substr(0, 2), 16)
			const g = parseInt(hex.substr(2, 2), 16)
			const b = parseInt(hex.substr(4, 2), 16)
			const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255
			return luminance > 0.5 ? '#000000' : '#ffffff'
		},
	},
}
</script>
<style scoped>
.highlight-color-picker {
	display: flex;
	align-items: center;
	gap: 8px;
}
</style>
