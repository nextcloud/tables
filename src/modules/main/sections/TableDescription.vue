<template>
	<div class="row first-row">
		<h1>
			{{ activeTable.emoji }}&nbsp;{{ activeTable.title }}
		</h1>
		<div class="light">
			<NcActions>
				<NcActionButton icon="icon-rename"
					:close-after-click="true"
					@click="editTable">
					{{ t('tables', 'Edit table') }}
				</NcActionButton>
			</NcActions>
		</div>
	</div>
</template>

<script>
import { mapGetters } from 'vuex'
import { NcActions, NcActionButton } from '@nextcloud/vue'
import { emit } from '@nextcloud/event-bus'

export default {
	name: 'TableDescription',
	components: {
		NcActions,
		NcActionButton,
	},
	computed: {
		...mapGetters(['activeTable']),
	},
	methods: {
		editTable() {
			emit('edit-table', this.activeTable.id)
		},
	},
}
</script>

<style lang="scss" scoped>

.light {
  opacity: .3;
}

.first-row:hover .light {
  opacity: 1;
}

</style>
