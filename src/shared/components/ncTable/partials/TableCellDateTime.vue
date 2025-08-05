<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<!-- TODO: ensure empty cells without default work -->
	<div class="cell-datetime">
		<div class="non-edit-mode" v-if="!isEditing" @click="startEditing">
			{{ getValue }}
		</div>
		<div v-else
			ref="editingContainer"
			class="inline-editing-container"
			tabindex="0"
			@keydown.enter="saveChanges"
			@keydown.escape="cancelEdit">
			<div class="datetime-picker-container" :class="{ 'is-loading': localLoading }">
				<NcDateTimePickerNative
					v-model="editDateTimeValue"
					:type="getPickerType"
					:label="getPlaceholder"
					:disabled="localLoading || !canEditCell()" />
				<div v-if="canBeCleared" class="icon-close make-empty" @click="emptyValue" />
			</div>
			<div v-if="localLoading" class="loading-indicator">
				<div class="icon-loading-small icon-loading-inline" />
			</div>
		</div>
	</div>
</template>

<script>
import { NcDateTimePickerNative, NcButton } from '@nextcloud/vue'
import Moment from '@nextcloud/moment'
import cellEditMixin from '../mixins/cellEditMixin.js'
import { translate as t } from '@nextcloud/l10n'
import { get } from 'jquery'

export default {
	name: 'TableCellDateTime',

	components: {
		NcDateTimePickerNative,
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
			type: String,
			default: null,
		},
	},

	data() {
		return {
			editDateTimeValue: null,
		}
	},

	computed: {
		getValue() {
			// default value is for the form, if you want to present the date from today, you should use the calculating column
			if (!this.value || this.value === 'none' || this.value === 'today' || this.value === 'now') {
				return ''
			}
			return this.column.formatValue(this.value)
		},

		getDefaultValue() {
			return this.column.formatValue(this.column.datetimeDefault)
		},

		getPlaceholder() {
			switch (this.column.type) {
			case 'datetime-date':
				return t('tables', 'Select a date')
			case 'datetime-time':
				return t('tables', 'Select a time')
			default:
				return t('tables', 'Select a date and time')
			}
		},

		getPickerType() {
			console.log('column', this.column)
			switch (this.column.type) {
			case 'datetime-date':
				return 'date'
			case 'datetime-time':
				return 'time'
			default:
				return 'datetime-local'
			}
		},

		canBeCleared() {
			return !this.column.mandatory
		},
	},

	watch: {
		isEditing(newValue) {
			if (newValue) {
				this.initEditValue()
				// Use a small delay to prevent the same click event that triggered editing
				// from immediately triggering the click outside handler
				this.$nextTick(() => {
					setTimeout(() => {
						document.addEventListener('click', this.handleClickOutside)
					}, 10)
				})
			} else {
				// Remove click outside listener
				document.removeEventListener('click', this.handleClickOutside)
			}
		},
	},

	methods: {
		t,

		initEditValue() {
			if (this.value !== null && this.value !== 'none') {
				const format = this.getDateFormat()
				
				if (this.column.type === 'datetime-time') {
					// For time-only values, use the same approach as DatetimeTimeForm
					const timeMoment = Moment(this.value, format)
					if (timeMoment.isValid()) {
						this.editDateTimeValue = timeMoment.toDate()
					} else {
						this.editDateTimeValue = new Date()
					}
				} else {
					// For date and datetime values, parse normally
					const parsedMoment = Moment(this.value, format)
					this.editDateTimeValue = parsedMoment.isValid() ? parsedMoment.toDate() : null
				}
			} else if ((this.value === null || this.value === '') && this.column.datetimeDefault) {
				// Handle default values
				if (this.column.datetimeDefault === 'now' || this.column.datetimeDefault === 'today') {
					this.editDateTimeValue = new Date()
				} else {
					this.editDateTimeValue = this.column.type === 'datetime-time' ? new Date() : null
				}
			} else {
				// For time columns, always provide a default Date object to prevent errors
				this.editDateTimeValue = this.column.type === 'datetime-time' ? new Date() : null
			}
		},

		getDateFormat() {
			switch (this.column.type) {
			case 'datetime-date':
				return 'YYYY-MM-DD'
			case 'datetime-time':
				return 'HH:mm'
			default:
				return 'YYYY-MM-DD HH:mm'
			}
		},

		emptyValue() {
			this.editDateTimeValue = null
		},

		async saveChanges() {
			if (this.localLoading) {
				return
			}

			let newValue = null

			if (this.editDateTimeValue === null) {
				newValue = 'none'
			} else {
				const format = this.getDateFormat()
				newValue = Moment(this.editDateTimeValue).format(format)
			}

			if (newValue === this.value) {
				this.isEditing = false
				return
			}

			const success = await this.updateCellValue(newValue)

			if (!success) {
				this.cancelEdit()
			}

			this.localLoading = false
			this.isEditing = false
		},

		handleClickOutside(event) {
			if (this.$refs.editingContainer && !this.$refs.editingContainer.contains(event.target)) {
				this.saveChanges()
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.cell-datetime {
	width: 100%;

	.non-edit-mode {
		cursor: pointer;
		min-height: 20px;
	}

	> div:first-child {
		cursor: pointer;
		text-align: right;
	}
}

.inline-editing-container {
	.datetime-picker-container {
		display: flex;
		align-items: center;
		width: 100%;

		&.is-loading {
			opacity: 0.7;
		}

		div {
			width: 100%;
		}
	}

	.datetime-picker-container input {
		width: 100%;
	}

}

.editor-buttons {
	display: flex;
	gap: 8px;
	margin-top: 8px;
	align-items: center;
}

.make-empty {
	padding-left: 15px;
	cursor: pointer;
}

.icon-loading-inline {
	margin-left: 4px;
}

:deep(input[type="datetime-local"]),
:deep(input[type="date"]),
:deep(input[type="time"]) {
	width: 100%;
}
</style>
