<template>
	<div
		:class="{ 'box-1': size === 1, 'box-2': size === 2, 'box-3': size === 3, 'box-4': size === 4, 'light': background === 'light' }"
		:style="{ minHeight: `${minHeight}px` }">
		<h2 v-if="header">
			<router-link :to="'/table/' + tableId">
				{{ header }}
			</router-link>
		</h2>
		<div class="body">
			<slot />
		</div>
		<div v-if="footer" class="footer">
			{{ footer }}
		</div>
		<div v-if="loading" class="icon-loading top-right" />
	</div>
</template>

<script>

export default {
	name: 'TableBox',
	props: {
		header: {
			type: String,
			default: '',
		},
		footer: {
			type: String,
			default: '',
		},
		size: {
			type: Number, // 1-4
			default: 2,
		},
		minHeight: {
			type: Number,
			default: 130,
		},
		loading: {
			type: Boolean,
			default: false,
		},
		icon: {
			type: String,
			default: '',
		},
		tableId: {
			type: Number,
			default: null,
		},
		background: {
			type: String,
			default: 'normal', // light
		},
	},
}
</script>
<style type="text/css" scoped>

.top-right {
	position: absolute;
	right: 15px;
	top: 15px;
}

.header .icon {
	padding-right: 5px;
	font-size: larger;
}

</style>
