<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="format-style-picker">
		<div class="format-style-picker__row">
			<label class="format-style-picker__label">{{ t('tables', 'Background') }}</label>
			<div class="format-style-picker__color-wrap">
				<input v-model="localBg"
					class="format-style-picker__color-input"
					type="color"
					:title="t('tables', 'Background color')"
					@input="onBgInput"
					@change="onBgChange" />
				<input v-model="localBg"
					class="format-style-picker__hex-input"
					type="text"
					maxlength="7"
					placeholder="#rrggbb"
					@change="onBgChange" />
				<NcButton v-if="localBg"
					type="tertiary-no-background"
					:aria-label="t('tables', 'Clear background color')"
					@click="clearBg">
					<template #icon>
						<Close :size="16" />
					</template>
				</NcButton>
			</div>
		</div>

		<div class="format-style-picker__row">
			<label class="format-style-picker__label">{{ t('tables', 'Text color') }}</label>
			<div class="format-style-picker__color-wrap">
				<input v-model="localFg"
					class="format-style-picker__color-input"
					type="color"
					:title="t('tables', 'Text color')"
					@input="onFgInput"
					@change="onFgChange" />
				<input v-model="localFg"
					class="format-style-picker__hex-input"
					type="text"
					maxlength="7"
					placeholder="#rrggbb"
					@change="onFgChange" />
				<NcButton v-if="localFg"
					type="tertiary-no-background"
					:aria-label="t('tables', 'Clear text color')"
					@click="clearFg">
					<template #icon>
						<Close :size="16" />
					</template>
				</NcButton>
			</div>
			<span v-if="contrastWarning"
				class="format-style-picker__contrast-warning"
				:title="t('tables', 'Low contrast — text may be hard to read (below 4.5:1 WCAG AA)')">
				⚠
			</span>
		</div>

		<div class="format-style-picker__row">
			<label class="format-style-picker__label">{{ t('tables', 'Style') }}</label>
			<div class="format-style-picker__toggles">
				<NcButton :type="localFontWeight === 'bold' ? 'primary' : 'tertiary'"
					:aria-label="t('tables', 'Bold')"
					:aria-pressed="localFontWeight === 'bold'"
					class="format-style-picker__toggle"
					@click="toggleFontWeight">
					<strong>B</strong>
				</NcButton>
				<NcButton :type="localFontStyle === 'italic' ? 'primary' : 'tertiary'"
					:aria-label="t('tables', 'Italic')"
					:aria-pressed="localFontStyle === 'italic'"
					class="format-style-picker__toggle"
					@click="toggleFontStyle">
					<em>I</em>
				</NcButton>
				<NcButton :type="localTextDecoration === 'strikethrough' ? 'primary' : 'tertiary'"
					:aria-label="t('tables', 'Strikethrough')"
					:aria-pressed="localTextDecoration === 'strikethrough'"
					class="format-style-picker__toggle"
					@click="toggleStrikethrough">
					<s>S</s>
				</NcButton>
				<NcButton :type="localTextDecoration === 'underline' ? 'primary' : 'tertiary'"
					:aria-label="t('tables', 'Underline')"
					:aria-pressed="localTextDecoration === 'underline'"
					class="format-style-picker__toggle"
					@click="toggleUnderline">
					<u>U</u>
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import { NcButton } from '@nextcloud/vue'
import Close from 'vue-material-design-icons/Close.vue'

function getRelativeLuminance(hex) {
	const r = parseInt(hex.slice(1, 3), 16) / 255
	const g = parseInt(hex.slice(3, 5), 16) / 255
	const b = parseInt(hex.slice(5, 7), 16) / 255
	const linearize = c => c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4)
	return 0.2126 * linearize(r) + 0.7152 * linearize(g) + 0.0722 * linearize(b)
}

function contrastRatio(hex1, hex2) {
	const l1 = getRelativeLuminance(hex1)
	const l2 = getRelativeLuminance(hex2)
	const lighter = Math.max(l1, l2)
	const darker = Math.min(l1, l2)
	return (lighter + 0.05) / (darker + 0.05)
}

function isValidHex(v) {
	return /^#[0-9a-fA-F]{6}$/.test(v)
}

export default {
	name: 'FormatStylePicker',

	components: {
		NcButton,
		Close,
	},

	props: {
		format: {
			type: Object,
			default: () => ({}),
		},
	},

	emits: ['update:format'],

	data() {
		return {
			localBg: this.format.backgroundColor || '',
			localFg: this.format.textColor || '',
			localFontWeight: this.format.fontWeight || null,
			localFontStyle: this.format.fontStyle || null,
			localTextDecoration: this.format.textDecoration || null,
			userOverrodeFg: false,
		}
	},

	computed: {
		contrastWarning() {
			if (!this.userOverrodeFg || !isValidHex(this.localBg) || !isValidHex(this.localFg)) return false
			return contrastRatio(this.localBg, this.localFg) < 4.5
		},
	},

	watch: {
		format: {
			handler(val) {
				this.localBg = val.backgroundColor || ''
				this.localFg = val.textColor || ''
				this.localFontWeight = val.fontWeight || null
				this.localFontStyle = val.fontStyle || null
				this.localTextDecoration = val.textDecoration || null
			},
			deep: true,
		},
	},

	methods: {
		onBgInput(e) {
			this.localBg = e.target.value
		},

		onBgChange() {
			if (!isValidHex(this.localBg) && this.localBg !== '') return
			if (isValidHex(this.localBg) && !this.userOverrodeFg) {
				const L = getRelativeLuminance(this.localBg)
				this.localFg = L > 0.179 ? '#000000' : '#ffffff'
			}
			this.emitUpdate()
		},

		onFgInput(e) {
			this.localFg = e.target.value
		},

		onFgChange() {
			if (!isValidHex(this.localFg) && this.localFg !== '') return
			this.userOverrodeFg = true
			this.emitUpdate()
		},

		clearBg() {
			this.localBg = ''
			this.emitUpdate()
		},

		clearFg() {
			this.localFg = ''
			this.userOverrodeFg = false
			this.emitUpdate()
		},

		toggleFontWeight() {
			this.localFontWeight = this.localFontWeight === 'bold' ? null : 'bold'
			this.emitUpdate()
		},

		toggleFontStyle() {
			this.localFontStyle = this.localFontStyle === 'italic' ? null : 'italic'
			this.emitUpdate()
		},

		toggleStrikethrough() {
			this.localTextDecoration = this.localTextDecoration === 'strikethrough' ? null : 'strikethrough'
			this.emitUpdate()
		},

		toggleUnderline() {
			this.localTextDecoration = this.localTextDecoration === 'underline' ? null : 'underline'
			this.emitUpdate()
		},

		emitUpdate() {
			const fmt = {}
			if (isValidHex(this.localBg)) fmt.backgroundColor = this.localBg
			if (isValidHex(this.localFg)) fmt.textColor = this.localFg
			if (this.localFontWeight) fmt.fontWeight = this.localFontWeight
			if (this.localFontStyle) fmt.fontStyle = this.localFontStyle
			if (this.localTextDecoration) fmt.textDecoration = this.localTextDecoration
			this.$emit('update:format', fmt)
		},
	},
}
</script>

<style lang="scss" scoped>
.format-style-picker {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);

	&__row {
		display: flex;
		align-items: center;
		gap: calc(var(--default-grid-baseline) * 2);
	}

	&__label {
		min-width: 90px;
		font-size: 0.9em;
		color: var(--color-text-lighter);
	}

	&__color-wrap {
		display: flex;
		align-items: center;
		gap: var(--default-grid-baseline);
	}

	&__color-input {
		width: 36px;
		height: 36px;
		border: 2px solid var(--color-border-maxcontrast);
		border-radius: var(--border-radius);
		padding: 2px;
		cursor: pointer;
		background: none;
	}

	&__hex-input {
		width: 90px;
		border: 2px solid var(--color-border-maxcontrast);
		border-radius: var(--border-radius);
		padding: 4px 8px;
		font-family: monospace;
		background-color: var(--color-main-background);
		color: var(--color-main-text);

		&:focus {
			border-color: var(--color-primary-element);
			outline: none;
		}
	}

	&__contrast-warning {
		color: var(--color-warning);
		font-size: 1.1em;
		cursor: help;
	}

	&__toggles {
		display: flex;
		gap: var(--default-grid-baseline);
	}

	&__toggle {
		min-width: 36px;
		font-size: 1em;
	}
}
</style>
