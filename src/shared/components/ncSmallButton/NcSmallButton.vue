<template>
	<NcButton :aria-label="ariaLabel" class="button-small" @click="submit">
		<slot />
	</NcButton>
</template>
<script>
import { NcButton } from '@nextcloud/vue'

export default {

	components: {
		NcButton,
	},

	props: {
		ariaLabel: {
		      type: String,
		      default: null,
		    },
	},

	methods: {
		submit() {
			this.$emit('click')
		},
	},

}
</script>
<style lang="scss" scoped>

	.button-small {
		min-height: auto !important;
		padding: 1px 2px !important;
		margin: 0 0 0 4px !important;
	}

</style>
