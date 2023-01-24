<template>
	<div>
		<div class="row first-row">
			<div class="col-3">
				<h1>{{ t('tables', 'All tables') }}</h1>
			</div>
		</div>
		<div class="row space-LR space-T">
			<div class="fix-col-4" style="display: block;">
				<TableBox v-for="table in sortedTables"
					:key="table.id"
					:header="table.title"
					:table-id="table.id">
					<div class="row">
						<div class="col-2">
							{{ t('tables', 'Owner') }}
						</div>
						<div class="col-2">
							<NcUserBubble :user="table.ownership" :display-name="table.ownerDisplayName" />
						</div>
						<div class="col-2">
							{{ t('tables', 'Created at') }}
						</div>
						<div class="col-2">
							{{ table.createdAt | niceDateTime('l') }}
							{{ table.createdAt | niceDateTime('LT') }}
						</div>
					</div>
				</TableBox>
			</div>
		</div>
		<NcEmptyContent v-if="!tables || tables.length === 0" icon="icon-category-organization">
			{{ t('tables', 'No tables') }}
			<template #desc>
				{{ t('tables', 'Please create a table on the left.') }}
			</template>
		</NcEmptyContent>
	</div>
</template>

<script>
import { mapState, mapGetters } from 'vuex'
import TableBox from '../shared/components/ncCard/TableBox.vue'
import { NcEmptyContent, NcUserBubble } from '@nextcloud/vue'
import formatting from '../shared/mixins/formatting.js'

export default {
	name: 'Startpage',
	components: {
		TableBox,
		NcEmptyContent,
		NcUserBubble,
	},
	mixins: [formatting],
	data() {
		return {
		}
	},
	computed: {
		...mapState(['tables']),
		...mapGetters(['activeTable']),
		sortedTables() {
			return [...this.tables].sort((a, b) => a.title.localeCompare(b.title))
		},
	},
}
</script>
