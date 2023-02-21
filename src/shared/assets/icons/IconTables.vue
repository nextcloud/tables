<template>
	<span :aria-hidden="!title"
		:aria-label="title"
		class="material-design-icon tables-icon"
		role="img"
		v-bind="$attrs"
		@click="$emit('click', $event)">
		<svg version="1.0"
			xmlns="http://www.w3.org/2000/svg"
			xmlns:xlink="http://www.w3.org/1999/xlink"
			:fill="fillColor"
			:height="size"
			:width="size"
			viewBox="0 0 415 415">
			<g>
				<g>
					<rect x="150"
						y="115"
						width="120"
						height="60" />
				</g>
				<g>
					<rect x="150"
						y="315"
						width="120"
						height="60" />
				</g>
				<g>
					<rect x="300"
						y="115"
						width="120"
						height="60" />
				</g>
				<g>
					<rect x="150"
						y="215"
						width="120"
						height="60" />
				</g>
				<g>
					<rect x="0"
						y="215"
						width="120"
						height="60" />
				</g>
				<g>
					<rect x="300"
						y="315"
						width="120"
						height="60" />
				</g>
				<g>
					<rect x="300"
						y="215"
						width="120"
						height="60" />
				</g>
				<g>
					<rect y="315"
						width="120"
						height="60" />
				</g>
				<g>
					<rect x="0"
						y="115"
						width="120"
						height="60" />
				</g>
				<g>
					<rect y="15"
						width="415"
						height="50" />
				</g>
			</g>
		</svg>
	</span>
</template>

<script>
export default {
	name: 'IconTables',
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
