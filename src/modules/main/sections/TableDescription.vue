<template>
	<div>
		<div class="row first-row">
			<h1>
				{{ activeTable.emoji }}&nbsp;{{ activeTable.title }}
			</h1>
		</div>
		<div class="row space-LR space-T">
			<p v-if="!columns || columns.length === 0">
				{{ t('tables', 'There are no columns yet, click on the three-dot menu next to the table title ahead and create some.') }}
			</p>
		</div>
	</div>
</template>

<script>
import { mapState, mapGetters } from 'vuex'
import tablePermissions from '../mixins/tablePermissions.js'

export default {
	name: 'TableDescription',
	mixins: [tablePermissions],
	computed: {
		...mapState({
			columns: state => state.data.columns,
			rows: state => state.data.rows,
		}),
		...mapGetters(['activeTable']),
	},
}
</script>
