<template>
	<NcEmptyContent :title="t('tables', 'No columns')"
		:description="t('tables', 'We need at least one column, please be so kind and create one.')">
		<template #icon>
			{{ activeTable.emoji }}
		</template>
		<template #action>
			<NcButton :aria-label="t('table', 'Create column')" type="primary" @click="$emit('create-column')">
				{{ t('tables', 'Create column') }}
			</NcButton>
		</template>
	</NcEmptyContent>
</template>
<script>
import { NcEmptyContent, NcButton } from '@nextcloud/vue'
import { mapGetters, mapState } from 'vuex'

export default {
	name: 'EmptyTable',
	components: {
		NcEmptyContent,
		NcButton,
	},
	computed: {
		...mapState(['tables']),
		...mapGetters(['activeTable']),
	},

}
</script>

<style scoped>

:deep(.empty-content__icon) {
	font-size: xxx-large;
	opacity: 1;
}

</style>
