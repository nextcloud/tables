<template>
	<table v-if="column" style="width: 100%;" />
</template>

<script>

export default {
	name: 'TextLinkTableDisplay',
	props: {
		column: {
			type: Object,
			default: null,
		},
	},
}
</script>
<style scoped>

table td {
	padding-right: 10px;
}

</style>
