<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="yes-no-cell" @click="toggleValue">
		<div v-if="!localLoading">
			<RadioboxBlankIcon v-if="value !== null && value === 'false'" />
			<CheckCircleOutline v-if="value !== null && value === 'true'" />
		</div>
		<div v-else class="icon-loading" />
	</div>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { mapActions, mapState } from 'pinia'
import { useDataStore } from '../../../../store/data.js'
import RadioboxBlankIcon from 'vue-material-design-icons/RadioboxBlank.vue'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'

export default {
	name: 'TableCellYesNo',
	components: {
		RadioboxBlankIcon,
		CheckCircleOutline,
	},

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
			type: String,
			default: 'false',
		},
	},

	data() {
		return {
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

		async toggleValue() {
			this.localLoading = true

			const newValue = this.value === 'true' ? 'false' : 'true'
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
			} else {
				this.$emit('update:value', newValue)
			}

			this.localLoading = false
		},
	},
}
</script>

<style scoped>
.yes-no-cell {
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
}

.icon-loading {
    width: 24px;
    height: 24px;
}
</style>
