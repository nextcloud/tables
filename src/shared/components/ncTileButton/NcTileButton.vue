<template>
	<button class="tile-button" :style="{ backgroundColor: backgroundColor }" @click="submit">
		<div class="icon-wrapper">
			<slot name="icon" />
		</div>
		<div class="text-wrapper">
			{{ buttonText }}
		</div>
	</button>
</template>

<script>
export default {
	props: {
		buttonText: {
			type: String,
			default: null,
		},
		type: {
			type: String,
			default: 'primary',
		},
	},
	computed: {
		backgroundColor() {
			switch (this.type) {
			case 'primary': return 'var(--color-primary)'
			case 'secondary': return 'var(--color-primary-light)'
			case 'tertiary': return 'inheriate'
			case 'error': return 'var(--color-error)'
			case 'warning': return 'var(--color-warning)'
			case 'success': return 'var(--color-success)'
			default: return 'var(--color-primary)'
			}
		},
	},
	methods: {
		submit() {
			this.$emit('click')
		},
	},
}
</script>

<style>
.tile-button {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	width: 120px;
	height: 120px;
	border-radius: 10px !important;
	border: none !important;
}

.icon-wrapper {
	font-size: 36px;
	margin-bottom: 10px;
}

.text-wrapper {
	font-size: 16px;
}
</style>
