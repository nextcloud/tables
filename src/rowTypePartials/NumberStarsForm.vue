<template>
	<div class="row">
		<div class="fix-col-1" :class="{ mandatory: column.mandatory }">
			{{ column.title }}
		</div>
		<div class="fix-col-1" :class="{ 'space-B': !column.description }" style="display: inline-flex;">
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
		<div class="fix-col-1 hide-s">
			&nbsp;
		</div>
		<div v-if="column.description" class="fix-col-1 p span space-B">
			<div class="hint-padding-left">
				{{ column.description }}
			</div>
		</div>
		<div v-if="!column.description" class="fix-col-1 p span space-B hide-s">
			&nbsp;
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
			type: Number,
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
			if (this.localValue > 0) {
				this.localValue--
			}
		},
	},
}
</script>
