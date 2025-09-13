<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="cell-stars" :style="{ opacity: !canEditCell() ? 0.6 : 1 }">
		<div v-if="!isEditing" @click="startEditing">
			<div class="stars-display">
				{{ getValue }}
			</div>
		</div>
		<div v-else
			ref="editingContainer"
			class="inline-editing-container"
			tabindex="0"
			@keydown.enter="saveChanges"
			@keydown.escape="cancelEdit">
			<div class="align-center" :class="{ 'is-loading': localLoading }">
				<div class="clickable-stars">
					<span v-for="star in 5"
						:key="star"
						class="star"
						:class="{ 'filled': star <= editValue, 'clickable': !localLoading && canEditCell() }"
						:aria-label="t('tables', 'Set {star} stars', { star })"
						@click="setStar(star)">
						{{ star <= editValue ? '★' : '☆' }}
					</span>
				</div>
				<div v-if="localLoading" class="icon-loading-small icon-loading-inline" />
			</div>
		</div>
	</div>
</template>

<script>
import cellEditMixin from '../mixins/cellEditMixin.js'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'TableCellStars',

	mixins: [cellEditMixin],

	props: {
		column: {
			type: Object,
			default: () => {},
		},
		rowId: {
			type: Number,
			default: null,
		},
		value: {
			type: Number,
			default: 0,
		},
	},

	computed: {
		getValue() {
			const starEmpty = '☆'
			const starFull = '★'
			const v = this.value
			return starFull.repeat(v) + starEmpty.repeat(5 - v)
		},
	},

	watch: {
		isEditing(newValue) {
			if (newValue) {
				this.$nextTick(() => {
					document.addEventListener('click', this.handleClickOutside)
				})
			} else {
				document.removeEventListener('click', this.handleClickOutside)
			}
		},
	},

	methods: {
		t,

		setStar(starNumber) {
			if (!this.localLoading && this.canEditCell()) {
				// If clicking on a star that represents the current rating, clear to 0
				if (starNumber === this.editValue) {
					this.editValue = 0
				} else {
					this.editValue = starNumber
				}
			}
		},

		async saveChanges() {
			if (this.localLoading) {
				return
			}

			if (this.editValue === this.value) {
				this.isEditing = false
				return
			}

			const success = await this.updateCellValue(this.editValue)

			if (!success) {
				this.cancelEdit()
			}

			this.localLoading = false
			this.isEditing = false
		},

		handleClickOutside(event) {
			// Check if the click is outside the editing container
			if (this.$refs.editingContainer && !this.$refs.editingContainer.contains(event.target)) {
				this.saveChanges()
			}
		},
	},
}
</script>

<style scoped lang="scss">
.cell-stars {
	width: 100%;
}

.cell-stars > div:first-child {
	cursor: pointer;
}

.stars-display {
	font-size: 20px;
	cursor: pointer;
}

.align-center {
	align-items: center;
	display: flex;

	&.is-loading {
		opacity: 0.7;
	}
}

.clickable-stars {
	display: flex;
	align-items: center;
	gap: 2px;
}

.star {
	font-size: 1.4em;
	padding: 4px;
	transition: transform 0.1s ease;

	&.clickable {
		cursor: pointer;

		&:hover {
			transform: scale(1.1);
		}
	}

	&.filled {
		color: var(--color-warning);
	}
}

.icon-loading-inline {
	margin-inline-start: 4px;
}
</style>
