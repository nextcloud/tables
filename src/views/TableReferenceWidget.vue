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
			<span v-if="emoji"
				class="table-emoji">
				{{ emoji }}
			</span>
			<TablesIcon v-else
				:size="50" />
		</div>
		<div class="tables-table--info">
			<div class="line">
				<strong>
					<a :href="richObject.link" target="_blank">
						{{ richObject.title }}
					</a>
				</strong>
			</div>
			<div class="description">
				{{ richObject.description }}
			</div>
			<div class="last-edited">
				{{ richObject.lastEditBy }}
				<NcUserBubble :user="richObject.lastEditBy"
					:display-name="richObject.lastEditBy" />
			</div>
		</div>
	</div>
</template>

<script>
import TablesIcon from '../icons/TablesIcon.vue'
import NcUserBubble from '@nextcloud/vue/dist/Components/NcUserBubble.js'

export default {
	name: 'TableReferenceWidget',

	components: {
		TablesIcon,
		NcUserBubble,
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
}
</style>
