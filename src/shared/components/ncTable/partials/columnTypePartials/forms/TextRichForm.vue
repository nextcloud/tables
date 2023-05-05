<template>
	<div style="width: 100%">
		<!-- default -->
		<div class="row">
			<div class="fix-col-4">
				{{ t('tables', 'Default') }}
			</div>
			<div class="fix-col-4 space-B">
				<NcEditor :text.sync="defaultText" height="small" />
			</div>
		</div>
	</div>
</template>

<script>
import NcEditor from '../../../../ncEditor/NcEditor.vue'

export default {
	name: 'TextRichForm',

	components: {
		NcEditor,
	},

	props: {
		textDefault: {
			type: String,
			default: '',
		},
	},

	computed: {
		defaultText: {
			get() { return this.textDefault },
			set(defaultText) { this.$emit('update:textDefault', defaultText) },
		},
	},

}
</script>
