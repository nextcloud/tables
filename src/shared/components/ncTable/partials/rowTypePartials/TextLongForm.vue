<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<RowFormWrapper :title="column.title" :mandatory="column.viewColumnInformation?.mandatory ?? column.mandatory" :description="column.description">
		<TiptapMenuBar
			:value.sync="localValue"
			:text-length-limit="getTextLimit"
			:readonly="column.viewColumnInformation?.readonly"
			@input="updateText" />
	</RowFormWrapper>
</template>

<script>
import TiptapMenuBar from '../TiptapMenuBar.vue'
import RowFormWrapper from './RowFormWrapper.vue'

export default {
	components: {
		TiptapMenuBar,
		RowFormWrapper,
	},
	props: {
		column: {
			type: Object,
			default: null,
		},
		value: {
			type: String,
			default: null,
		},
	},
	computed: {
		localValue: {
			get() {
				return (this.value !== null)
					? this.value
					: ((this.column.textDefault !== undefined)
						? this.column.textDefault
						: '')
			},
			set(v) {
				this.$emit('update:value', v)
			},
		},
		getTextLimit() {
			if (this.column.textMaxLength === -1) {
				return null
			} else {
				return this.column.textMaxLength
			}
		},
	},
	methods: {
		updateText(text) {
			this.localValue = text
		},
	},
}
</script>
