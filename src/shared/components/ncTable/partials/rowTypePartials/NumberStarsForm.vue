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
			<div class="space-L-small">
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
				if (this.value !== null) {
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
			return '★'.repeat(this.localValue) + '☆'.repeat(5 - this.localValue)
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
