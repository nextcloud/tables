<template>
	<div class="row space-T">
		<div :class="{ 'fix-col-3': hasHeadSlot, 'fix-col-4': !hasHeadSlot }">
			<div class="row">
				<div class="title fix-col-4">
					{{ title }}<span v-if="mandatory" :title="t('tables', 'This field is mandatory')">*</span>
				</div>
				<p v-if="description" class="fix-col-4 span">
					{{ description }}
				</p>
			</div>
		</div>
		<div v-if="hasHeadSlot" class="fix-col-1 end">
			<slot name="head" />
		</div>
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

	computed: {
		hasHeadSlot() {
			return !!this.$slots.head?.[0]
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
