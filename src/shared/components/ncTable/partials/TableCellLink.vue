<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<LinkWidget :thumbnail-url="getValueObject.thumbnailUrl"
			:icon-url="getValueObject.icon"
			:title="getValueObject.title"
			:subline="getValueObject.subline"
			:url="getValueObject.resourceUrl"
			:truncate-length="20"
			:icon-size="25"
			:hide-default-icon="true"
			:underline-title="true" />
	</div>
</template>

<script>
import generalHelper from '../../../mixins/generalHelper.js'
import LinkWidget from './LinkWidget.vue'

export default {
	name: 'TableCellLink',

	components: {
		LinkWidget,
	},

	mixins: [generalHelper],

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

	computed: {
		getValueObject() {
			if (this.hasJsonStructure(this.value)) {
				const valueObject = JSON.parse(this.value)
				delete valueObject.subline
				if (!valueObject.resourceUrl && valueObject.value) {
					valueObject.resourceUrl = valueObject.value
				}
				return valueObject || {}
			} else {
				return {
					thumbnailUrl: null,
					iconUrl: null,
					title: this.value,
					resourceUrl: this.value,
				}
			}
		},
	},

}
</script>

<style lang="scss" scoped>

div {
	// min-width: 80px;
}

</style>
