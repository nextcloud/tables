<template>
	<div>
		<h3>{{ t('tables', 'Table') }}</h3>
		<h2>
			{{ activeTable.title }}&nbsp;
			<Actions>
				<ActionButton icon="icon-template-add">
					<template #icon>
						<TableColumnPlusAfter :size="20" decorative title="" />
					</template>
					{{ t('tables', 'Add a new column') }}
				</ActionButton>
				<ActionButton icon="icon-menu">
					<template #icon>
						<TableEdit :size="20" decorative title="" />
					</template>
					{{ t('tables', 'Edit columns') }}
				</ActionButton>
			</Actions>
		</h2>
		<h4>{{ t('tables', 'Default view') }}</h4>
	</div>
</template>

<script>
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import TableColumnPlusAfter from 'vue-material-design-icons/TableColumnPlusAfter'
import TableEdit from 'vue-material-design-icons/TableEdit'

export default {
	name: 'TableDescription',
	components: {
		Actions,
		ActionButton,
		TableColumnPlusAfter,
		TableEdit,
	},
	props: {
		activeTable: {
			type: Object,
			default: null,
		},
	},
}
</script>
