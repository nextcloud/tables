<!--
  - @copyright Copyright (c) 2023 Florian Steffens <flost-dev@mailbox.org>
  -
  - @author 2023 Florian Steffens <flost-dev@mailbox.org>
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
		<div class="nc-table">
			<NcTable
				:rows="richObject.rows"
				:columns="richObject.columns"
				:can-create-rows="false"
				:can-edit-rows="false"
				:can-delete-rows="false"
				:can-create-columns="false"
				:can-edit-columns="false"
				:can-delete-columns="false"
				:can-delete-table="false"
				:can-select-rows="false" />
		</div>
	</div>
</template>

<script>
import NcTable from '../shared/components/ncTable/NcTable.vue'

export default {

	components: {
		NcTable,
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
	},
}
</script>
<style lang="scss" scoped>

	div {
		width: 100%;
	}

	h2 {
		padding-left: calc(var(--default-grid-baseline) * 3);
		padding-top: calc(var(--default-grid-baseline) * 3);
	}

	.nc-table {
		margin-left: calc(var(--default-grid-baseline) * 2);
	}

	.nc-table :deep(.container) {
		max-height: 50vh;
	}

</style>
