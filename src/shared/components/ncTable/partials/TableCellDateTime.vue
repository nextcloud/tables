<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="cell-datetime" :style="cellStyle">
		<!-- View Mode -->
		<div v-if="!isEditing"
			class="non-edit-mode"
			role="button"
			tabindex="0"
			aria-label="Edit date/time"
			@click="handleStartEditing">
			@keydown.enter="handleStartEditing"
			@keydown.space.prevent="handleStartEditing"
			{{ getValue }}
		</div>
		<!-- Edit Mode -->
		<div v-else
			ref="editingContainer"
			class="inline-editing-container"
			role="group"
			aria-label="Edit date/time"
			tabindex="0"
			@keydown.enter="commitEditValue"
			@keydown.escape="cancelEdit">
			<div class="datetime-picker-container" :class="{ 'is-loading': localLoading }">
				<NcDateTimePickerNative v-model="editDateTimeValue"
					:type="getPickerType"
					:disabled="localLoading || !canEditCell()" 
				/>
				<!-- Clear button -->
				<div v-if="canBeCleared"
					class="icon-close make-empty"
					role="button"
					tabindex="0"
					:aria-label="t('tables', 'Clear value')"
					@click="clearPickerEditValue"
					@keydown.enter="clearPickerEditValue"
					@keydown.space.prevent="clearPickerEditValue"
				/>
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
		cellstyle() {
			return {
				opacity: !this.canEditCell() ? 0.6 : 1,
			}
		},
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
				this.initializePickerValue()
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

		/*
		When editing a cell we have the following possible scenarios:
		1) No value (null, 'none', or '') [never set or explicitly empty; we don't really care in this context I don't think?]
		2) Pre-existing value

		For "no value" we then have two scenarios:
		- column default to use (if null or ''; not if expplicitly 'none')
		- no column default

		For pre-existing value we have just one scenario:
		- parse for validity
		*/
		/**
		 * - Sets default values if applicable: When the cell is empty (null or '') and the column has a default setting
		 * - Handles empty/unset Values: When no value exists and the column has no default configured
		 * - Parses existing values: Converts stored datetime strings ('YYYY-MM-DD HH:mm') back into JavaScript Date objects
		 */
		setInitialPickerEditValue() {
			// null = never set (mandatory or pre-storage/pre-normalized)
			// 'none' = intentionally empty (normalized)
			// '' = intentionally empty (pre-storage/pre-normalized)
			const isEmptyorUnset = !this.value || this.value === 'none'

			// handle existing cell value (i.e. we need to parse it)
			if (!isEmptyOrUnset) {
				const inputFormat = this.getDateFormat()
				const outputFormat = this.getLocaleDateFormat()
				const parsedMoment = Moment(this.value, inputFormat).format(outputFormat) // storage->locale
					
				if (this.column.type === 'datetime-time') {
					this.editDateTimeValue = parsedMoment.isValid()
						? parsedMoment.toDate()
						: new Date() // default Date object to prevent errors
				} else {
					this.editDateTimeValue = parsedMoment.isValid()
						? parsedMoment.toDate()
						: null
				}
				return
			}

			// handle explicitly cleared or unset cell that has a default (via column settings)
			if ((this.value === null || this.value === '') && this.column.datetimeDefault) {
				if (this.column.datetimeDefault === 'now' || this.column.datetimeDefault === 'today') {
					this.editDateTimeValue = new Date()
				} else {
					this.editDateTimeValue = this.column.type === 'datetime-time'
						? new Date() // default Date object to prevent errors
						: null
				}
				return
			}
			
			// handle unset cell that does not have a default 			
			this.editDateTimeValue = this.column.type === 'datetime-time'
				? new Date() // default Date object to prevent errors
				: null
		},

		clearPickerEditValue() {
			this.editDateTimeValue = null
		},

		async commitEditValue() {
			if (this.localLoading) {
				return
			}

			let newValue = null

			if (this.editDateTimeValue === null) {
				newValue = 'none'
			} else {
				const inputFormat = this.getLocaleDateFormat()
				const outputFormat = this.getDateFormat()
				newValue = Moment(this.editDateTimeValue, inputFormat).format(outputFormat) // opposite of edit mode above (locale->storage)
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
				this.commitEditValue()
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

		// Moment specific
		getLocaleDateFormat() {
			switch (this.column.type) {
			case 'datetime-date':
				return 'll'
			case 'datetime-time':
				return 'LT'
			default:
				return 'lll'
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
