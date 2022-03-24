<template>
	<div class="row">
		<div class="fix-col-1" :class="{ mandatory: column.mandatory }">
			{{ column.title }}
		</div>
		<div class="fix-col-1" :class="{ 'margin-bottom': !column.description }">
			<button @click="less">
				-
			</button>
			<div style="font-size: 1.4em; padding: 7px;">
				{{ getStars }}
			</div>
			<button @click="more">
				+
			</button>
		</div>
		<div class="fix-col-2 p span margin-bottom">
			<div class="hint-padding-left">
				{{ column.description }}
			</div>
		</div>
	</div>
</template>

<script>

export default {
	name: 'NumberStarsForm',
	props: {
		column: {
			type: Object,
			default: null,
		},
		value: {
			type: String,
			default: null,
		},
	},
	data() {
		return {
		}
	},
	computed: {
		localValue: {
			get() {
				if (this.value) {
					return this.value
				} else {
					if (this.column.numberDefault !== undefined) {
						this.$emit('update:value', this.column.numberDefault)
						return this.column.numberDefault
					} else {
						return null
					}
				}
			},
			set(v) { this.$emit('update:value', v) },
		},
		getStars() {
			const starEmpty = '☆'
			const starFull = '★'
			const v = this.localValue
			let res = starEmpty + starEmpty + starEmpty + starEmpty + starEmpty
			if (v && v === 1) {
				res = starFull + starEmpty + starEmpty + starEmpty + starEmpty
			} else if (v && v === 2) {
				res = starFull + starFull + starEmpty + starEmpty + starEmpty
			} else if (v && v === 3) {
				res = starFull + starFull + starFull + starEmpty + starEmpty
			} else if (v && v === 4) {
				res = starFull + starFull + starFull + starFull + starEmpty
			} else if (v && v === 5) {
				res = starFull + starFull + starFull + starFull + starFull
			}
			return res
		},
	},
	methods: {
		more() {
			if (this.localValue < 5) {
				this.localValue++
			}
		},
		less() {
			if (this.localValue > 1) {
				this.localValue--
			}
		},
	},
}
</script>
<style scoped>

.hint-padding-left {
	padding-left: 20px;
}

@media only screen and (max-width: 641px) {
	.hint-padding-left {
		padding-left: 0;
	}
}

</style>
