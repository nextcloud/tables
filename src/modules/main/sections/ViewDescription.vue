<template>
	<div class="row first-row">
		<h1>
			{{ activeElement.emoji }}&nbsp;{{ activeElement.title }}
		</h1>
	</div>
</template>

<script>

export default {
	name: 'ViewDescription',

	props: {
		activeElement: {
			type: Object,
			default: null,
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

.row.first-row {
	position: sticky;
	left: 0;
	top: 0;
	z-index: 15;
	background-color: var(--color-main-background-translucent);
	align-items: baseline;
}

.user-bubble {
	padding-left: calc(var(--default-grid-baseline) * 2);
}

</style>
