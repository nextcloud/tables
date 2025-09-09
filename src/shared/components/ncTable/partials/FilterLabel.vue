<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="filter" :class="[type]">
		{{ labelText }}
		<button @click="actionDelete">
			<Close :size="14" />
		</button>
	</div>
</template>
<script>
import Close from 'vue-material-design-icons/Close.vue'
import { MagicFields } from '../mixins/magicFields.js'
import { Filter, FilterIds } from '../mixins/filter.js'

export default {

	components: {
		Close,
	},

	props: {
		operator: {
		      type: Filter,
		      default: null,
		    },
		value: {
		      type: String,
		      default: '',
		    },
		type: {
		      type: String,
		      default: 'highlighted', // highlighted, outlined
		    },
		id: {
			type: String,
			default: null,
		},
	},

	computed: {
		getValue() {
			let value = this.value
			Object.values(MagicFields).forEach(field => {
				value = value.replace('@' + field.id, field.label)
			})
			return value
		},
		labelText() {
			if (this.operator.id === FilterIds.IsEmpty) {
				return this.operator.getOperatorLabel()
			} else {
				return this.operator.getOperatorLabel() + ' "' + this.getValue + '"'
			}
		},
	},

	methods: {
		actionDelete() {
			this.$emit('delete-filter', this.id)
		},
	},

}
</script>
<style lang="scss" scoped>

.highlighted {
	background-color: var(--color-border-maxcontrast);
	color: var(--color-primary-element-text-dark);
}

.outlined {
	border: 1px solid;
}

.filter {
	padding-top: 1px;
	padding-inline: calc(var(--default-grid-baseline) * 2) calc(var(--default-grid-baseline) * 6);
	padding-bottom: calc(var(--default-grid-baseline) * .5);
	font-size: 0.9em;
	border-radius: var(--border-radius-pill);
	margin-bottom: calc(var(--default-grid-baseline) * 1);
	width: fit-content;
	color: var(--color-primary-element-text-dark);
	line-height: initial;
}

button {
	position: absolute;
	padding-top: 1px;
	min-height: auto;
	padding-bottom: 1px;
	padding-inline: 2px;
	margin: 0;
	margin-inline-start: 4px;
}

</style>
