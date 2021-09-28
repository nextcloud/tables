<template>
	<div>
		<h3>{{ t('tables', 'Table') }}</h3>
		<h2>{{ activeTable.title }}</h2>
		<h4>{{ t('tables', 'Default view') }}</h4>
	</div>
</template>

<script>

export default {
	name: 'TableDescription',
	props: {
		activeTable: {
			type: Object,
			default: null,
		},
	},
}
</script>
