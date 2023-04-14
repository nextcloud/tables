<template>
	<div style="width: 100%">
		<!-- default -->
		<div class="row space-T">
			<div class="fix-col-4">
				{{ t('tables', 'Default') }}
			</div>
			<div class="fix-col-2 align-center">
				<NcButton type="tertiary" :aria-label="t('tables', 'Reduce stars')" @click="less">
					<template #icon>
						<Minus :size="20" />
					</template>
				</NcButton>
				<div class="stars">
					{{ getStars }}
				</div>
				<NcButton type="tertiary" :aria-label="t('tables', 'Increase stars')" @click="more">
					<template #icon>
						<Plus :size="20" />
					</template>
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import { NcButton } from '@nextcloud/vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Minus from 'vue-material-design-icons/Minus.vue'

export default {
	name: 'NumberStarsForm',

	components: {
		Plus,
		Minus,
		NcButton,
	},

	props: {
		numberDefault: {
			type: Number,
			default: 0,
		},
	},
	computed: {
		defaultNum: {
			get() { return this.numberDefault },
			set(defaultNum) { this.$emit('update:numberDefault', parseFloat(defaultNum)) },
		},
		getStars() {
			return '★'.repeat(this.defaultNum) + '☆'.repeat(5 - this.defaultNum)
		},
	},

	methods: {
		more() {
			if (this.defaultNum < 5) {
				this.defaultNum++
			}
		},
		less() {
			if (this.defaultNum > 0) {
				this.defaultNum--
			}
		},
	},

}
</script>
<style lang="scss" scoped>

.align-center {
	align-items: center;
	display: inline-flex;
}

.stars {
	font-size: 1.4em;
	padding: 7px;
}

</style>
