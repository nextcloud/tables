<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="cell-datetime" :style="{ opacity: !canEditCell() ? 0.6 : 1 }">
		<div v-if="!isEditing" class="non-edit-mode" role="button" aria-label="Edit date/time"
			tabindex="0"
			@click="handleStartEditing">
			{{ getValue }}
		</div>
		<div v-else
			ref="editingContainer"
			class="inline-editing-container"
			tabindex="0"
			role="group"
			aria-label="Edit date/time"
			@keydown.enter="saveChanges"
			@keydown.escape="cancelEdit">
			<div class="datetime-picker-container" :class="{ 'is-loading': localLoading }">
				<NcDateTimePickerNative
					v-model="editDateTimeValue"
					:type="getPickerType"
					:disabled="localLoading || !canEditCell()" />
				<div v-if="canBeCleared" class="icon-close make-empty" role="button"
					:aria-label="t('tables', 'Clear value')" @click="emptyValue" />
			</div>
			<div v-if="localLoading" class="loading-indicator">
				<div class="icon-loading-small icon-loading-inline" />
			</div>
		</div>
	</div>
</template>

<script>
import { NcDateTimePickerNative } from '@nextcloud/vue'
import Moment from '@nextcloud/moment'
import cellEditMixin from '../mixins/cellEditMixin.js'
import { translate as t } from '@nextcloud/l10n'
import rowHelper from '../mixins/rowHelper.js'

export default {
	name: 'TableCellDateTime',

	components: {
		NcDateTimePickerNative,
	},

	mixins: [cellEditMixin, rowHelper],

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
			isInitialEditClick: false,
		}
	},

	computed: {
		getValue() {
			if (!this.value || this.value === 'none' || this.value === 'today' || this.value === 'now') {
				return ''
			}
			return this.column.formatValue(this.value)
		},

		getPickerType() {
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
			return !this.isMandatory(this.column)
		},
	},

	watch: {
		isEditing(newValue) {
			if (newValue) {
				this.initEditValue()
				this.$nextTick(() => {
					document.addEventListener('click', this.handleClickOutside)
				})
			} else {
				document.removeEventListener('click', this.handleClickOutside)
				this.isInitialEditClick = false
			}
		},
	},

	methods: {
		t,

		handleStartEditing(event) {
			this.isInitialEditClick = true
			this.startEditing()
			// Stop the event from propagating to avoid immediate click outside
			event.stopPropagation()
		},

		initEditValue() {
			if (this.value !== null && this.value !== 'none') {
				const format = this.getDateFormat()

				if (this.column.type === 'datetime-time') {
					const timeMoment = Moment(this.value, format)
					if (timeMoment.isValid()) {
						this.editDateTimeValue = timeMoment.toDate()
					} else {
						this.editDateTimeValue = new Date()
					}
				} else {
					const parsedMoment = Moment(this.value, format)
					this.editDateTimeValue = parsedMoment.isValid() ? parsedMoment.toDate() : null
				}
			} else if ((this.value === null || this.value === '') && this.column.datetimeDefault) {
				if (this.column.datetimeDefault === 'now' || this.column.datetimeDefault === 'today') {
					this.editDateTimeValue = new Date()
				} else {
					this.editDateTimeValue = this.column.type === 'datetime-time' ? new Date() : null
				}
			} else {
				// For time columns, have default Date object to prevent errors
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
			// Ignore the initial click that started editing
			if (this.isInitialEditClick) {
				this.isInitialEditClick = false
				return
			}

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
		text-align: end;
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

		/*
		The fully accessible view with labels is always present in the row-editing-dialog. So it's ok if we do not have labels in inline editing.
		*/
		:deep(.native-datetime-picker--label) {
			display: none;
		}
	}

	.datetime-picker-container input {
		width: 100%;
	}

}

.make-empty {
	padding-inline-start: 15px;
	cursor: pointer;
}

.icon-loading-inline {
	margin-inline-start: 4px;
}

:deep(input[type="datetime-local"]),
:deep(input[type="date"]),
:deep(input[type="time"]) {
	width: 100%;
}
</style>
