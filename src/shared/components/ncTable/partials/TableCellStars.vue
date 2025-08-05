<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="cell-stars">
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
				<NcButton type="tertiary"
					:aria-label="t('tables', 'Reduce stars')"
					:disabled="localLoading || editValue <= 0 || !canEditCell()"
					@click="less">
					<template #icon>
						<span class="minus-icon">-</span>
					</template>
				</NcButton>
				<div class="stars">
					{{ getEditValue }}
				</div>
				<NcButton type="tertiary"
					:aria-label="t('tables', 'Increase stars')"
					:disabled="localLoading || editValue >= 5 || !canEditCell()"
					@click="more">
					<template #icon>
						<span class="plus-icon">+</span>
					</template>
				</NcButton>
				<div v-if="localLoading" class="icon-loading-small icon-loading-inline" />
			</div>
		</div>
	</div>
</template>

<script>
import { NcButton } from '@nextcloud/vue'
import cellEditMixin from '../mixins/cellEditMixin.js'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'TableCellStars',

	components: {
		NcButton,
	},

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
		getEditValue() {
			const starEmpty = '☆'
			const starFull = '★'
			const v = this.editValue
			return starFull.repeat(v) + starEmpty.repeat(5 - v)
		},
	},

	watch: {
		isEditing(newValue) {
			if (newValue) {
				this.$nextTick(() => {
					// Add click outside listener
					document.addEventListener('click', this.handleClickOutside)
				})
			} else {
				// Remove click outside listener
				document.removeEventListener('click', this.handleClickOutside)
			}
		},
	},

	methods: {
		t,

		more() {
			if (this.editValue < 5 && !this.localLoading && this.canEditCell()) {
				this.editValue++
			}
		},

		less() {
			if (this.editValue > 0 && !this.localLoading && this.canEditCell()) {
				this.editValue--
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

.stars {
	font-size: 1.4em;
	padding: 7px;
}

.editor-buttons {
	display: flex;
	gap: 8px;
	margin-top: 8px;
	align-items: center;
}

.icon-loading-inline {
	margin-left: 4px;
}

.minus-icon, .plus-icon {
	font-size: 20px;
	font-weight: bold;
}
</style>
