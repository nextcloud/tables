<template>
	<div style="width: 100%">
		<div class="row">
			<div class="col-4 title space-T">
				{{ t('tables', 'Options') }}
			</div>
			<div v-for="opt in localSelectionOptions" :key="opt.id" class="col-4 inline">
				<NcCheckboxRadioSwitch :value="'' + opt.id" type="radio" :checked.sync="localSelectionDefault" />
				<input :value="opt.label" @input="updateLabel(opt.id, $event)">
				<NcButton type="tertiary" :aria-label="t('tables', 'Delete option')" @click="deleteOption(opt.id)">
					<template #icon>
						<DeleteOutline :size="20" />
					</template>
				</NcButton>
			</div>
			<NcButton :aria-label="t('tables', 'Add option')" @click="addOption">
				{{ t('tables', 'Add option') }}
			</NcButton>
			<p class="span">
				{{ t('tables', 'You can set a default value by clicking on one of the radio buttons next to the label fields.') }}
				<a v-if="localSelectionDefault" @click="localSelectionDefault = ''">{{ t('tables', 'Click here to unset default selection.') }}</a>
			</p>
		</div>
	</div>
</template>

<script>
import { NcCheckboxRadioSwitch, NcButton } from '@nextcloud/vue'
import DeleteOutline from 'vue-material-design-icons/DeleteOutline.vue'

export default {
	name: 'SelectionForm',
	components: {
		NcCheckboxRadioSwitch,
		NcButton,
		DeleteOutline,
	},
	props: {
		selectionOptions: {
			type: Array,
			default: null,
		},
		selectionDefault: {
			type: String,
			default: null,
		},
	},
	computed: {
		localSelectionDefault: {
			get() {
				return this.selectionDefault
			},
			set(value) {
				this.$emit('update:selectionDefault', '' + value)
			},
		},
		localSelectionOptions: {
			get() {
				if (this.selectionOptions) {
					return this.selectionOptions
				}
				return [
					{
						id: 0,
						label: t('tables', 'First option'),
					},
					{
						id: 1,
						label: t('tables', 'Second option'),
					},
				]
			},
			set(value) {
				this.$emit('update:selectionOptions', [...value])
			},
		},
	},
	methods: {
		updateLabel(id, e) {
			const i = this.localSelectionOptions.findIndex((obj) => obj.id === id)
			const tmp = [...this.localSelectionOptions]
			tmp[i].label = e.target.value
			this.localSelectionOptions = tmp
		},
		addOption() {
			const nextId = this.getNextId()
			const options = [...this.localSelectionOptions]
			options.push({
				id: nextId,
				label: '',
			})
			this.localSelectionOptions = options
		},
		getNextId() {
			return Math.max(...this.localSelectionOptions.map(item => item.id)) + 1
		},
		deleteOption(id) {
			const i = this.localSelectionOptions.findIndex((obj) => obj.id === id)
			const tmpOptions = [...this.localSelectionOptions]
			tmpOptions.splice(i, 1)
			this.localSelectionOptions = tmpOptions
		},
	},
}
</script>
<style lang="scss" scoped>

.inline {
	display: inline-flex;
}

input {
	margin-top: 8px;
	margin-left: calc(var(--default-grid-baseline) * 1);
}

.col-4.inline {
	margin-left: calc(var(--default-grid-baseline) * 3);
}

</style>
