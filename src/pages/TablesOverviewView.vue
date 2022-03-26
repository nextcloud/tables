<template>
	<div>
		<div class="row padding-left">
			<div class="col-4 row-with-margin">
				<h2>{{ t('tables', 'All tables') }}</h2>
			</div>
			<div class="col-4">
				<TableBox v-for="table in tables"
					:key="table.id"
					:header="table.title"
					:table-id="table.id">
					<div class="row">
						<div class="col-2">
							{{ t('tables', 'Owner') }}
						</div>
						<div class="col-2">
							<UserBubble :user="table.ownership" display-name="" />
						</div>
						<div class="col-2">
							{{ t('tables', 'Created at') }}
						</div>
						<div class="col-2">
							{{ table.createdAt | niceDateTime('l') }}
							{{ table.createdAt | niceDateTime('LT') }}
						</div>
						<div class="col-2 light">
							{{ t('tables', 'Internal ID') }}
						</div>
						<div class="col-2 light">
							{{ table.id }}
						</div>
					</div>
				</TableBox>
			</div>
		</div>
		<EmptyContent v-if="!tables || tables.length === 0" icon="icon-category-organization">
			{{ t('tables', 'No tables') }}
			<template #desc>
				{{ t('tables', 'Please create a table on the left.') }}
			</template>
		</EmptyContent>
	</div>
</template>

<script>
import { mapState, mapGetters } from 'vuex'
import TableBox from '../partials/TableBox'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import UserBubble from '@nextcloud/vue/dist/Components/UserBubble'
import formatting from '../mixins/formatting'

export default {
	name: 'TablesOverviewView',
	components: {
		TableBox,
		EmptyContent,
		UserBubble,
	},
	mixins: [formatting],
	data() {
		return {
		}
	},
	computed: {
		...mapState(['tables']),
		...mapGetters(['activeTable']),
	},
}
</script>
