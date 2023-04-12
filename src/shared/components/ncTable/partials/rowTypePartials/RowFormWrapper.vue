<template>
	<div class="row space-T">
		<div class="fix-col-4">
			<div class="title">
				{{ title }}<span v-if="mandatory" :title="t('tables', 'This field is mandatory')">*</span>
			</div>
		</div>
		<p v-if="description" class="fix-col-4 span">
			{{ description }}
		</p>
		<div :class="[ `fix-col-${width}` ]" class="slot">
			<slot />
		</div>
	</div>
</template>

<script>

export default {
	name: 'RowFormWrapper',
	props: {
		mandatory: {
			type: Boolean,
			default: false,
		},
		title: {
			type: String,
			default: '',
		},
		length: {
			type: Number,
			default: null,
		},
		maxLength: {
			type: Number,
			default: null,
		},
		description: {
			type: String,
			default: null,
		},
		width: {
			type: Number,
			default: 4,
		},
	},
}
</script>
<style scoped lang="scss">

.title {
	font-weight: bold;
	margin-bottom: calc(var(--default-grid-baseline) * 1);
}

.slot {
	align-items: baseline;
}

</style>
