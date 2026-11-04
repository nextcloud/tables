<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="format-style-picker">
		<div class="format-style-picker__row">
			<label class="format-style-picker__label">
				<span class="format-style-picker__label-text">{{ t('tables', 'Background') }}</span>
				<input v-model="localBg"
					class="format-style-picker__color-input"
					type="color"
					:title="t('tables', 'Background color')"
					@change="onBgChange">
			</label>
			<div class="format-style-picker__color-wrap">
				<input v-model="localBg"
					class="format-style-picker__hex-input"
					type="text"
					maxlength="7"
					placeholder="#rrggbb"
					:aria-label="t('tables', 'Background color as a hex value')"
					@change="onBgChange">
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
			<label class="format-style-picker__label">
				<span class="format-style-picker__label-text">{{ t('tables', 'Text color') }}</span>
				<input v-model="localFg"
					class="format-style-picker__color-input"
					type="color"
					:title="t('tables', 'Text color')"
					@change="onFgChange">
			</label>
			<div class="format-style-picker__color-wrap">
				<input v-model="localFg"
					class="format-style-picker__hex-input"
					type="text"
					maxlength="7"
					placeholder="#rrggbb"
					:aria-label="t('tables', 'Text color as a hex value')"
					@change="onFgChange">
				<NcButton v-if="localFg"
					type="tertiary-no-background"
					:aria-label="t('tables', 'Clear text color')"
					@click="clearFg">
					<template #icon>
						<Close :size="16" />
					</template>
				</NcButton>
			</div>
			<span class="format-style-picker__contrast-region" role="status">
				<span v-if="contrastWarning" class="format-style-picker__contrast-warning">
					<AlertOutline :size="18" />
					{{ t('tables', 'Low contrast — text may be hard to read (below 4.5:1 WCAG AA)') }}
				</span>
			</span>
		</div>

		<div class="format-style-picker__row" role="group" :aria-label="t('tables', 'Style')">
			<span class="format-style-picker__label" aria-hidden="true">{{ t('tables', 'Style') }}</span>
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
import AlertOutline from 'vue-material-design-icons/AlertOutline.vue'
import Close from 'vue-material-design-icons/Close.vue'

function expandHex(hex) {
	if (hex.length !== 4) {
		return hex
	}
	return '#' + hex[1] + hex[1] + hex[2] + hex[2] + hex[3] + hex[3]
}

function getRelativeLuminance(shortOrLongHex) {
	const hex = expandHex(shortOrLongHex)
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
	return /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(v)
}

/**
 * @param {string} variable the CSS custom property to read
 * @param {string} fallback used when the property is unset or not a plain hex colour
 * @return {string}
 */
function themeColor(variable, fallback) {
	if (typeof window === 'undefined' || typeof window.getComputedStyle !== 'function') {
		return fallback
	}
	const value = window.getComputedStyle(document.body).getPropertyValue(variable).trim()
	return isValidHex(value) ? value : fallback
}

export default {
	name: 'FormatStylePicker',

	components: {
		AlertOutline,
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
			autoAppliedFg: null,
			autoAppliedBg: null,
		}
	},

	computed: {
		/**
		 * @return {boolean}
		 */
		fgIsUserChosen() {
			return !!this.localFg && this.localFg !== this.autoAppliedFg
		},

		contrastWarning() {
			const hasBg = isValidHex(this.localBg)
			const hasFg = isValidHex(this.localFg)
			if (!hasBg && !hasFg) {
				return false
			}
			const background = hasBg ? this.localBg : themeColor('--color-main-background', '#ffffff')
			const text = hasFg ? this.localFg : themeColor('--color-main-text', '#000000')
			return contrastRatio(background, text) < 4.5
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
		onBgChange() {
			if (!isValidHex(this.localBg) && this.localBg !== '') return
			this.autoAppliedBg = null
			if (isValidHex(this.localBg) && !this.fgIsUserChosen) {
				this.localFg = getRelativeLuminance(this.localBg) > 0.179 ? '#000000' : '#ffffff'
				this.autoAppliedFg = this.localFg
			}
			this.emitUpdate()
		},

		onFgChange() {
			if (!isValidHex(this.localFg) && this.localFg !== '') return
			this.autoAppliedFg = null
			this.emitUpdate()
		},

		clearBg() {
			this.localBg = ''
			this.autoAppliedBg = null
			if (this.localFg && this.localFg === this.autoAppliedFg) {
				this.localFg = ''
				this.autoAppliedFg = null
			}
			this.emitUpdate()
		},

		clearFg() {
			this.localFg = ''
			this.autoAppliedFg = null
			if (this.localBg && this.localBg === this.autoAppliedBg) {
				this.localBg = ''
				this.autoAppliedBg = null
			}
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

		pairColours() {
			const hasBg = isValidHex(this.localBg)
			const hasFg = isValidHex(this.localFg)
			if (hasBg === hasFg) {
				return
			}
			if (hasBg) {
				this.localFg = getRelativeLuminance(this.localBg) > 0.179 ? '#000000' : '#ffffff'
				this.autoAppliedFg = this.localFg
				return
			}
			this.localBg = getRelativeLuminance(this.localFg) > 0.179 ? '#000000' : '#ffffff'
			this.autoAppliedBg = this.localBg
		},

		emitUpdate() {
			this.pairColours()
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
		display: flex;
		align-items: center;
		gap: calc(var(--default-grid-baseline) * 2);
		font-size: 0.9em;
		color: var(--color-text-lighter);
	}

	&__label-text {
		min-width: 90px;
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

		&:focus-visible {
			border-color: var(--color-primary-element);
			outline: 2px solid var(--color-primary-element);
			outline-offset: 2px;
		}
	}

	&__contrast-warning {
		display: flex;
		align-items: center;
		gap: calc(var(--default-grid-baseline) * 0.5);
		color: var(--color-warning-text, var(--color-main-text));
		font-size: 0.9em;
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
