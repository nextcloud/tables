<template>
	<span :aria-hidden="!title"
		:aria-label="title"
		class="material-design-icon tables-icon"
		role="img"
		v-bind="$attrs"
		@click="$emit('click', $event)">
		<svg
			:fill="fillColor"
			:width="size"
			:height="size"
			enable-background="new 0 0 415 415"
			version="1.1"
			viewBox="0 0 415 415"
			xml:space="preserve"
			xmlns="http://www.w3.org/2000/svg">
			<rect x="150" y="115" width="120" height="60" />
			<rect x="150" y="315" width="120" height="60" />
			<rect x="300" y="115" width="120" height="60" />
			<rect x="150" y="215" width="120" height="60" />
			<rect x="0" y="215" width="120" height="60" />
			<rect x="300" y="315" width="120" height="60" />
			<rect x="300" y="215" width="120" height="60" />
			<rect y="315" width="120" height="60" />
			<rect x="0" y="115" width="120" height="60" />
			<rect y="15" width="415" height="50" />
		</svg>
	</span>
</template>

<script>
export default {
	name: 'TablesIcon',
	props: {
		title: {
			type: String,
			default: '',
		},
		fillColor: {
			type: String,
			default: 'currentColor',
		},
		size: {
			type: Number,
			default: 24,
		},
	},
}
</script>
