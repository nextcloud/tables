<template>
	<div>
		<div class="row first-row">
			<div class="col-3">
				<h1>{{ t('tables', 'All tables') }}</h1>
			</div>
		</div>
		<div class="row space-LR space-T">
			<div class="fix-col-4" style="display: block;">
				<TableBox v-for="table in tables"
					:key="table.id"
					:header="table.title"
					:table-id="table.id">
					<div class="row">
						<div class="col-2">
							{{ t('tables', 'Owner') }}
						</div>
						<div class="col-2">
							<UserBubble :user="table.ownership" :display-name="table.ownerDisplayName" />
						</div>
						<div class="col-2">
							{{ t('tables', 'Created at') }}
						</div>
						<div class="col-2">
							{{ table.createdAt | niceDateTime('l') }}
							{{ table.createdAt | niceDateTime('LT') }}
						</div>
						<div class="col-4">
							<TableInfoPopover :table="table" />
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
import TableInfoPopover from '../partials/TableInfoPopover'

export default {
	name: 'TablesOverviewView',
	components: {
		TableInfoPopover,
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
