<template>
	<NcEmptyContent :title="t('tables', 'No columns')"
		:description="t('tables', 'We need at least one column, please be so kind and create one.')">
		<template #icon>
			{{ table.emoji }}
		</template>
		<template #action>
			<NcButton :aria-label="t('table', 'Create column')" type="primary" @click="createColumn()">
				{{ t('tables', 'Create column') }}
			</NcButton>
		</template>
	</NcEmptyContent>
</template>
<script>
import { NcEmptyContent, NcButton } from '@nextcloud/vue'
import { emit } from '@nextcloud/event-bus'

export default {
	name: 'EmptyTable',
	components: {
		NcEmptyContent,
		NcButton,
	},
	props: {
		table: {
			type: Object,
			default: null,
		},
	},
	methods: {
		createColumn() {
			emit('tables:column:create')
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
