<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="yes-no-cell" @click="toggleValue">
		<div class="inline-editing-container">
			<div v-if="!localLoading">
				<RadioboxBlankIcon v-if="value !== null && value === 'false'" />
				<CheckCircleOutline v-if="value !== null && value === 'true'" />
			</div>
			<div v-else class="icon-loading-small icon-loading-inline" />
		</div>
	</div>
</template>

<script>
import RadioboxBlankIcon from 'vue-material-design-icons/RadioboxBlank.vue'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'
import cellEditMixin from '../mixins/cellEditMixin.js'

export default {
	name: 'TableCellYesNo',
	components: {
		RadioboxBlankIcon,
		CheckCircleOutline,
	},

	mixins: [cellEditMixin],

	props: {
		value: {
			type: String,
			default: 'false',
		},
	},

	methods: {
		async toggleValue() {
			const newValue = this.value === 'true' ? 'false' : 'true'
			await this.updateCellValue(newValue)
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
</style>
