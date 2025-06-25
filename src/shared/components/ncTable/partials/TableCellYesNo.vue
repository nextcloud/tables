<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="inline-editing-container">
		<NcCheckboxRadioSwitch v-model="localValue" :loading="localLoading" type="switch" @update:modelValue="onToggle" />
	</div>
</template>

<script>
import cellEditMixin from '../mixins/cellEditMixin.js'
import { NcCheckboxRadioSwitch } from '@nextcloud/vue'

export default {
	name: 'TableCellYesNo',
	components: {
		NcCheckboxRadioSwitch,
	},

	mixins: [cellEditMixin],

	props: {
		value: {
			type: String,
			default: 'false',
		},
	},

	data() {
		return {
			localValue: this.value === 'true',
		}
	},

	watch: {
		value(newValue) {
			this.localValue = newValue === 'true'
		},
	},

	methods: {
		async onToggle() {
			const response = await this.updateCellValue(this.localValue)
			if (!response) {
				this.localValue = !this.localValue // revert to previous value
				return
			}
			this.isEditing = false
			this.localLoading = false
		},
	},
}
</script>
