<template>
	<div class="row space-T">
		<div class="fix-col-4 justify-between">
			<div class="title">
				{{ title }}{{ mandatory ? '*': '' }}
			</div>
			<div v-if="length && maxLength !== -1" class="p span" style="padding-bottom: 0; padding-top: 0;">
				{{ t('tables', 'length: {length}/{maxLength}', { length, maxLength }) }}
			</div>
		</div>
		<p v-if="description" class="fix-col-4 span">
			{{ description }}
		</p>
		<div class="slot" :class="{ 'fix-col-4': width === 4, 'fix-col-3': width === 3, 'fix-col-2': width === 2, 'fix-col-1': width === 1 }">
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
}

.slot {
	align-items: baseline;
}

</style>
