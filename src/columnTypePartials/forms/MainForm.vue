<template>
	<div style="width: 100%">
		<!-- title -->
		<div class="fix-col-1 mandatory" :class="{error: titleMissingError}">
			{{ t('tables', 'Title') }}
		</div>
		<div class="fix-col-3 margin-bottom" :class="{error: titleMissingError}">
			<input v-model="localTitle" :placeholder="t('tables', 'Enter a column title')">
		</div>

		<!-- description -->
		<div class="fix-col-1">
			{{ t('tables', 'Description') }}
		</div>
		<div class="fix-col-3 margin-bottom">
			<textarea v-model="localDescription" />
		</div>

		<!-- mandatory -->
		<div class="fix-col-1">
			{{ t('tables', 'Mandatory') }}
		</div>
		<div class="fix-col-3 margin-bottom">
			<CheckboxRadioSwitch type="switch" :checked.sync="localMandatory" />
		</div>

		<!-- order weight -->
		<div class="fix-col-1">
			{{ t('tables', 'Order weight') }}
		</div>
		<div class="fix-col-3 margin-bottom">
			<input v-model="localOrderWeight"
				type="number"
				max="100"
				min="0"
				step="1">
		</div>
	</div>
</template>

<script>
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'

export default {
	name: 'MainForm',
	components: {
		CheckboxRadioSwitch,
	},
	props: {
		title: {
			type: String,
			default: null,
		},
		description: {
			type: String,
			default: null,
		},
		mandatory: {
			type: Boolean,
			default: null,
		},
		orderWeight: {
			type: Number,
			default: null,
		},
		titleMissingError: {
			type: Boolean,
			default: false,
		},
	},
	computed: {
		localTitle: {
			get() { return this.title },
			set(title) { this.$emit('update:title', title) },
		},
		localDescription: {
			get() { return this.description },
			set(description) { this.$emit('update:description', description) },
		},
		localMandatory: {
			get() { return this.mandatory },
			set(mandatory) { this.$emit('update:mandatory', mandatory) },
		},
		localOrderWeight: {
			get() { return this.orderWeight },
			set(orderWeight) { this.$emit('update:orderWeight', parseInt(orderWeight)) },
		},
	},
}
</script>
