<template>
	<div style="width: 100%">
		<!-- default -->
		<div class="row">
			<div class="fix-col-4">
				{{ t('tables', 'Default') }}
			</div>
			<div class="fix-col-4 space-B">
				<textarea v-model="mutableColumn.textDefault" />
			</div>
		</div>

		<!-- max text length -->
		<div class="row">
			<div class="fix-col-4">
				{{ t('tables', 'Maximum text length') }}
			</div>
			<div class="fix-col-4">
				<input v-model="mutableColumn.textMaxLength"
					type="number"
					step="1"
					min="0">
			</div>
		</div>
	</div>
</template>

<script>

export default {
	name: 'TextLongForm',
	props: {
		column: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			mutableColumn: this.column,
		}
	},
	watch: {
		column() {
			this.mutableColumn = this.column
		},
	},
}
</script>
