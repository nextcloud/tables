/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { mapActions, mapState } from 'pinia'
import { useDataStore } from '../../../../store/data.js'

export default {
	props: {
		column: {
			type: Object,
			required: true,
		},
		rowId: {
			type: Number,
			required: true,
		},
		value: {
			required: true,
		},
	},

	data() {
		return {
			isEditing: false,
			editValue: '',
			localLoading: false,
		}
	},

	computed: {
		...mapState(useDataStore, {
			rowMetadata(state) {
				return state.getRowMetadata(this.rowId)
			},
		}),
	},

	methods: {
		...mapActions(useDataStore, ['updateRow']),

		startEditing() {
			this.editValue = this.value
			this.isEditing = true
			this.$nextTick(() => {
				this.$refs.input?.focus()
			})
		},

		async updateCellValue(newValue) {
			// Prevent multiple executions
			if (this.localLoading) {
				return false
			}

			this.localLoading = true

			const data = [{
				columnId: this.column.id,
				value: newValue,
			}]

			const res = await this.updateRow({
				id: this.rowId,
				isView: this.rowMetadata.isView,
				elementId: this.rowMetadata.elementId,
				data,
			})

			if (!res) {
				showError(t('tables', 'Could not update cell'))
				return false
			} else {
				this.$emit('update:value', newValue)
				return true
			}
		},

		cancelEdit() {
			this.isEditing = false
			this.editValue = this.value
		},
	},
}
