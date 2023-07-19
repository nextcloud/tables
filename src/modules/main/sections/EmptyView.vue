<template>
	<NcEmptyContent :title="t('tables', 'No columns selected')"
		:description="t('tables', 'The view is empty. Edit which columns should be displayed.')">
		<template #icon>
			{{ activeView.emoji }}
		</template>
		<template #action>
			<NcButton :aria-label="t('table', 'Edit view')" type="primary" @click="editView()">
				{{ t('tables', 'Edit view') }}
			</NcButton>
		</template>
	</NcEmptyContent>
</template>
<script>
import { NcEmptyContent, NcButton } from '@nextcloud/vue'
import { mapGetters } from 'vuex'
import { emit } from '@nextcloud/event-bus'

export default {
	name: 'EmptyView',
	components: {
		NcEmptyContent,
		NcButton,
	},
	computed: {
		...mapGetters(['activeView']),
	},
	methods: {
		editView() {
			emit('tables:view:edit', this.activeView)
		},
	},
}
</script>

<style scoped>

:deep(.empty-content__icon) {
	font-size: xxx-large;
	opacity: 1;
}

</style>
