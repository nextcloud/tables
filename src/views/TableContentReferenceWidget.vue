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
	<div v-if="richObject">
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
import CustomTable from '../shared/components/ncTable/sections/CustomTable.vue'
import { parseCol } from '../shared/components/ncTable/mixins/columnParser.js'
import { AbstractColumn } from '../shared/components/ncTable/mixins/columnClass.js'
import { MetaColumns } from '../shared/components/ncTable/mixins/metaColumns.js'

export default {
	name: 'TableContentReferenceWidget',

	components: {
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
			const columns = this.richObject.columns
			const columnIds = this.richObject.columnIds

			if (columns.length >= 0 && !(columns[0] instanceof AbstractColumn)) {
				let allColumns = columns.map(col => parseCol(col)).concat(MetaColumns.filter(col => columnIds.includes(col.id)))
				allColumns = allColumns.sort(function(a, b) {
					return columnIds.indexOf(a.id) - columnIds.indexOf(b.id)
				})
				return allColumns
			}
			return columns
		},
		rows() {
			return this.richObject.rows
		},
	},
}
</script>
