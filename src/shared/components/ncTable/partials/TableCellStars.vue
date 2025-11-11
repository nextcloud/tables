<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="cell-stars" :style="{ opacity: !canEditCell() ? 0.6 : 1 }">
		<div class="inline-editing-container">
			<div class="interactive-stars"
				@mouseleave="hoverValue = null">
				<span v-for="star in 5"
					:key="star"
					class="star"
					:class="{
						'filled': star <= displayValue,
						'clickable': isClickable,
						'hovering': hoverValue !== null
					}"
					:aria-label="t('tables', 'Set {star} stars', { star })"
					@mouseenter="hoverValue = star"
					@click="setStar(star)">
					{{ star <= displayValue ? '★' : '☆' }}
				</span>
			</div>
			<div v-if="localLoading" class="icon-loading-small icon-loading-inline" />
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import cellEditMixin from '../mixins/cellEditMixin.js'

export default {
	name: 'TableCellStars',

	mixins: [cellEditMixin],

	props: {
		value: {
			type: Number,
			default: 0,
		},
	},

	data() {
		return {
			hoverValue: null,
			editValue: this.value,
		}
	},

	computed: {
		displayValue() {
			return this.hoverValue !== null ? this.hoverValue : this.editValue
		},

		isClickable() {
			return !this.localLoading && this.canEditCell()
		},
	},

	watch: {
		value(newValue) {
			this.editValue = newValue
		},
	},

	methods: {
		t,

		setStar(starNumber) {
			if (this.isClickable) {
				// If clicking on a star that represents the current rating, clear to 0
				if (starNumber === this.value) {
					this.editValue = 0
				} else {
					this.editValue = starNumber
				}

				this.hoverValue = null
				this.saveChanges()
			}
		},

		async saveChanges() {
			if (this.localLoading) {
				return
			}

			if (this.editValue === this.value) {
				return
			}

			const success = await this.updateCellValue(this.editValue)

			if (!success) {
				this.editValue = this.value
			}

			this.localLoading = false
		},
	},
}
</script>

<style scoped lang="scss">
.cell-stars {
	width: 100%;
}

.inline-editing-container {
	display: flex;
	align-items: center;
}

.interactive-stars {
	display: flex;
	align-items: center;
	gap: 0;
}

.star {
	font-size: 20px;
	color: var(--color-main-text);
	padding: 0;
	transition: color 0.1s ease;

	&.clickable {
		cursor: pointer;
	}

	&.filled {
		color: var(--color-main-text);
	}

	&.hovering {
		&.filled {
			color: var(--color-text-maxcontrast);
		}
	}
}

.icon-loading-inline {
	margin-inline-start: 4px;
}
</style>
