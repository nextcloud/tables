<template>
	<div style="width: 100%">
		<!-- title -->
		<div class="fix-col-1 mandatory" :class="{error: titleMissingError}">
			{{ t('tables', 'Title') }}
		</div>
		<div class="fix-col-3 margin-bottom" :class="{error: titleMissingError}">
			<input v-model="localTitle" :placeholder="t('tables', 'Title for the column.')">
		</div>

		<!-- description -->
		<div class="fix-col-1">
			{{ t('tables', 'Description') }}
		</div>
		<div class="fix-col-3 margin-bottom">
			<textarea v-model="localDescription" />
		</div>

		<!-- prefix -->
		<div class="fix-col-1">
			{{ t('tables', 'prefix') }}
		</div>
		<div class="fix-col-3 margin-bottom">
			<input v-model="localPrefix">
		</div>

		<!-- suffix -->
		<div class="fix-col-1">
			{{ t('tables', 'suffix') }}
			<Popover>
				<template #trigger>
					<button class="icon-details" />
				</template>
				<p>
					{{ t('tables', 'Here is a good place to put your unit for example.') }}
				</p>
			</Popover>
		</div>
		<div class="fix-col-3 margin-bottom">
			<input v-model="localSuffix">
		</div>

		<!-- mandatory -->
		<div class="fix-col-1">
			{{ t('tables', 'mandatory') }}
			<Popover>
				<template #trigger>
					<button class="icon-details" />
				</template>
				<p>
					{{ t('tables', 'Check if this field is mandatory. If so, it will be required in every form.') }}
				</p>
			</Popover>
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
// import { showError, showInfo, showSuccess } from '@nextcloud/dialogs'

import Popover from '@nextcloud/vue/dist/Components/Popover'
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'

export default {
	name: 'MainForm',
	components: {
		Popover,
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
		prefix: {
			type: String,
			default: null,
		},
		suffix: {
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
		localPrefix: {
			get() { return this.prefix },
			set(prefix) { this.$emit('update:prefix', prefix) },
		},
		localSuffix: {
			get() { return this.suffix },
			set(suffix) { this.$emit('update:suffix', suffix) },
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
