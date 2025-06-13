<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="inline-editing-container">
		<NcCheckboxRadioSwitch v-model="localValue" :loading="localLoading" @update:modelValue="onToggle" />
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
			localValue: undefined,
		}
	},

	beforeMount() {
		this.localValue = this.value === 'true'
	},

	methods: {
		async onToggle() {
			const response = await this.updateCellValue(this.localValue)
			this.localValue = response
			this.localLoading = false
		},
	},
}
</script>
