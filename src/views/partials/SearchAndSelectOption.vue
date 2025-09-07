<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="icon-label-container">
		<IconTable v-if="type === 'table'" :size="40" />
		<IconView v-if="type === 'view'" :size="40" />
		<IconRow v-if="type === 'row'" :size="40" />

		<div class="content">
			<div class="labels">
				{{ emoji ? emoji + ' ' : '' }}{{ label }}
			</div>

			<div class="details">
				<NcUserBubble v-if="owner"
					:user="owner"
					:display-name="ownerDisplayName ?? owner" />&nbsp;
				<NcCounterBubble v-if="rowsCount !== null">
					{{ n('tables', '{nb} row', '{nb} rows', rowsCount, { nb: rowsCount}) }}
				</NcCounterBubble>
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

	filters: {
		truncate(string, num) {
			if (!string) {
				return ''
			}
			if (string.length >= num) {
				return string.substring(0, num) + '...'
			} else {
				return string
			}
		},
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
		img, :deep(svg) {
			margin-inline-end: calc(var(--default-grid-baseline) * 2);
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
