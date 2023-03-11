<!--
  - @copyright Copyright (c) 2023 Julien Veyssier <eneiluj@posteo.net>
  -
  - @author 2023 Julien Veyssier <eneiluj@posteo.net>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -->

<template>
	<div v-if="richObject" class="tables-table">
		<div class="tables-table--image">
			<TablesIcon
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
		</div>
	</div>
</template>

<script>
import TablesIcon from '../icons/TablesIcon.vue'
import { NcUserBubble, NcCounterBubble } from '@nextcloud/vue'

export default {
	name: 'TableReferenceWidget',

	components: {
		TablesIcon,
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
		margin-right: 12px;
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
