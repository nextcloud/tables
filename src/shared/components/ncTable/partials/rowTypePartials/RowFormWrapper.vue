<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="row space-T">
		<div :class="{ 'fix-col-3': hasHeadSlot, 'fix-col-4': !hasHeadSlot }">
			<div class="row">
				<div class="title fix-col-4">
					{{ title }}<span v-if="mandatory" :title="t('tables', 'This field is mandatory')">*</span>
					<NcLoadingIcon v-if="loading" />
				</div>
				<p v-if="description" class="fix-col-4 span">
					<pre>{{ description }}</pre>
				</p>
			</div>
		</div>
		<div v-if="hasHeadSlot" class="fix-col-1 end">
			<slot name="head" />
		</div>
		<div :class="[ `fix-col-${width}` ]" class="slot">
			<slot />
		</div>
	</div>
</template>

<script>
import { NcLoadingIcon } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'

export default {

	components: {
		NcLoadingIcon,
	},

	props: {
		mandatory: {
			type: Boolean,
			default: false,
		},
		title: {
			type: String,
			default: '',
		},
		length: {
			type: Number,
			default: null,
		},
		maxLength: {
			type: Number,
			default: null,
		},
		description: {
			type: String,
			default: null,
		},
		width: {
			type: Number,
			default: 4,
		},
		loading: {
		      type: Boolean,
		      default: false,
		    },
	},

	computed: {
		hasHeadSlot() {
			return !!this.$slots.head?.[0]
		},
	},
	methods: {
		t,
	},
}
</script>
<style scoped lang="scss">

.title {
	font-weight: bold;
	margin-bottom: calc(var(--default-grid-baseline) * 1);
}

.slot {
	align-items: baseline;
}

.material-design-icon.loading-icon {
	margin-inline-start: calc(var(--default-grid-baseline) * 1);
}

pre {
	white-space: pre-wrap;
	white-space: -moz-pre-wrap;
	white-space: -pre-wrap;
	white-space: -o-pre-wrap;
	word-wrap: break-word;
}

</style>
