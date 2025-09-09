<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="richObject" class="tables-table">
		<div class="tables-table--image">
			<IconTables v-if="getType === 'table'"
				:size="50" />
			<IconView v-if="getType === 'view'"
				:size="50" />
		</div>
		<div class="tables-table--info">
			<div class="line">
				<strong>
					<a :href="richObject.link" target="_blank">
						{{ richObject.emoji + ' ' + richObject.title }}
					</a>
				</strong>
			</div>
			<div class="details">
				<NcUserBubble :user="richObject.ownership"
					:display-name="richObject.ownerDisplayName" />&nbsp;
				<NcCounterBubble>{{ n('tables', '{nb} row', '{nb} rows', richObject.rowsCount, { nb: richObject.rowsCount}) }}</NcCounterBubble>
			</div>
			<!-- <div>
				{{ richObject.rows }}
			</div> -->
		</div>
	</div>
</template>

<script>
import IconTables from '../shared/assets/icons/IconTables.vue'
import IconView from 'vue-material-design-icons/Text.vue'
import { NcUserBubble, NcCounterBubble } from '@nextcloud/vue'
import { translatePlural as n } from '@nextcloud/l10n'

export default {

	components: {
		IconTables,
		IconView,
		NcUserBubble,
		NcCounterBubble,
	},

	props: {
		richObjectType: {
			type: String,
			default: '',
		},
		richObject: {
			type: Object,
			default: null,
		},
		accessible: {
			type: Boolean,
			default: true,
		},
	},

	computed: {
		emoji() {
			return this.richObject.emoji
		},
		getType() {
			return this.richObject?.type ?? null
		},
	},

	methods: {
		n,
	},
}
</script>

<style scoped lang="scss">
.tables-table {
	width: 100%;
	white-space: normal;
	padding: 12px;
	display: flex;

	a {
		padding: 0 !important;
		&:not(:hover) {
			text-decoration: unset !important;
		}

	}

	.line {
		font-size: 1.3em;
		padding-bottom: calc(var(--default-grid-baseline) * 2);
	}

	&--image {
		margin-inline-end: 12px;
		display: flex;
		align-items: center;
		.table-emoji {
			display: flex;
			align-items: center;
			height: 50px;
			font-size: 50px;
		}
	}

	.spacer {
		flex-grow: 1;
	}

	.details {
		display: inline-flex;
		align-items: self-start;
	}
}

:deep(.counter-bubble__counter) {
	max-width: fit-content !important;
}
</style>
