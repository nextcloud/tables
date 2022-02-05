<template>
	<div style="width: 100%">
		<!-- default -->
		<div class="row">
			<div class="fix-col-1">
				{{ t('tables', 'Default') }}
			</div>
			<div class="fix-col-3 margin-bottom">
				<input v-model="defaultText">
			</div>
			<!--
      <div v-if="type === 'longtext'" class="fix-col-3 margin-bottom">
        <textarea v-model="textDefault" />
      </div>
      -->
		</div>

		<!-- allowed pattern -->
		<div class="row">
			<div class="fix-col-1">
				{{ t('tables', 'Allowed pattern (regex)') }}
			</div>
			<div class="fix-col-3 margin-bottom">
				<input v-model="allowedPattern">
			</div>
		</div>

		<!-- max text length -->
		<div class="row">
			<div class="fix-col-1">
				{{ t('tables', 'Maximum text length') }}
			</div>
			<div class="fix-col-1">
        &nbsp;
			</div>
			<div class="fix-col-2 margin-bottom">
				<input v-model="maxLength"
					type="number"
					step="1"
					min="0">
			</div>
		</div>
	</div>
</template>

<script>
// import { showError, showInfo, showSuccess } from '@nextcloud/dialogs'

export default {
	name: 'TextlineForm',
	props: {
		textDefault: {
			type: String,
			default: '',
		},
		textAllowedPattern: {
			type: String,
			default: '',
		},
		textMaxLength: {
			type: Number,
			default: null,
		},
	},
	computed: {
		defaultText: {
			get() { return this.textDefault },
			set(defaultText) { this.$emit('update:textDefault', defaultText) },
		},
		allowedPattern: {
			get() { return this.textAllowedPattern },
			set(allowedPattern) { this.$emit('update:textAllowedPattern', allowedPattern) },
		},
		maxLength: {
			get() { return (this.textMaxLength && this.textMaxLength === -1) ? '' : this.textMaxLength },
			set(maxLength) { this.$emit('update:textMaxLength', parseFloat(maxLength)) },
		},
	},
}
</script>
