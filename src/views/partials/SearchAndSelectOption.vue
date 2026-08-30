<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="icon-label-container" :class="{ 'icon-label-container--compact': compact }">
		<IconTable v-if="type === 'table'" :size="iconSize" />
		<IconView v-if="type === 'view'" :size="iconSize" />
		<IconRow v-if="type === 'row'" :size="iconSize" />

		<div class="content">
			<div class="labels">
				{{ emoji ? emoji + ' ' : '' }}{{ label }}
			</div>

			<div class="details">
				<NcUserBubble v-if="owner"
					:user="owner"
					:display-name="ownerDisplayName ?? owner" />&nbsp;
				<NcCounterBubble v-if="rowsCount !== null" :count="rowsCount" />
				<div v-if="subline" class="subline p span">
					{{ subline }}
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import IconTable from '../../shared/assets/icons/IconTables.vue'
import IconView from 'vue-material-design-icons/Text.vue'
import IconRow from 'vue-material-design-icons/PageNextOutline.vue'
import { NcCounterBubble, NcUserBubble } from '@nextcloud/vue'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'

export default {

	components: {
		NcCounterBubble,
		NcUserBubble,
		IconTable,
		IconView,
		IconRow,
	},

	props: {
		label: {
			type: String,
			default: '',
		},
		type: {
			type: String,
			default: 'table',
		},
		emoji: {
			type: String,
			default: null,
		},
		owner: {
			type: String,
			default: null,
		},
		ownerDisplayName: {
			type: String,
			default: null,
		},
		rowsCount: {
			type: Number,
			default: null,
		},
		subline: {
			type: String,
			default: null,
		},
		compact: {
			type: Boolean,
			default: false,
		},
	},

	computed: {
		iconSize() {
			return this.compact ? 28 : 40
		},
	},

	methods: {
		t,
		n,
	},

}
</script>
<style lang="scss" scoped>

	.icon-label-container {
		display: flex;
		align-items: center;
		min-width: 0;

		img, :deep(svg) {
			flex: 0 0 auto;
			margin-inline-end: calc(var(--default-grid-baseline) * 2);
		}
	}

	.icon-label-container--compact {
		width: 100%;
		overflow: hidden;

		.content {
			display: flex;
			align-items: center;
			min-width: 0;
			gap: calc(var(--default-grid-baseline) * 2);
		}

		.content .labels {
			overflow: hidden;
			margin-bottom: 0;
			text-overflow: ellipsis;
			white-space: nowrap;
			font-size: inherit;
		}

		.content .details {
			flex: 0 0 auto;
			align-items: center;
		}

		.subline {
			display: none;
		}
	}

	.labels {
		display: block;
	}

	.content {
		display: block;
	}

	.content .labels {
		font-size: 1.3em;
		font-weight: bold;
		margin-bottom: 8px;
	}

	.content .details {
		display: inline-flex;
	}

	:deep(.counter-bubble__counter) {
		max-width: fit-content !important;
		height: 100%;
	}

</style>
