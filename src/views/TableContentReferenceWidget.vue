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
		<h2>{{ richObject.emoji }}&nbsp;{{ richObject.title }}</h2>
		<CustomTable
			:columns="columns"
			:rows="richObject.rows"
			:view="null"
			:view-setting="{}"
			:read-only="true" />
	</div>
</template>

<script>
import TablesIcon from '../icons/TablesIcon.vue'
import { NcUserBubble, NcCounterBubble } from '@nextcloud/vue'
import CustomTable from '../shared/components/ncTable/sections/CustomTable.vue'
import { parseCol } from '../shared/components/ncTable/mixins/columnParser.js'

export default {
	name: 'TableContentReferenceWidget',

	components: {
		TablesIcon,
		NcUserBubble,
		NcCounterBubble,
		CustomTable,
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
		columns() {
			return this.richObject.columns.map(col => parseCol(col))
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
    flex-direction: column;

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
