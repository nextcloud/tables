<template>
	<div style="width: 100%">
		<div class="row">
			<div class="col-4 title space-T">
				{{ t('tables', 'Options') }}
			</div>
			<div v-for="opt in localSelectionOptions" :key="opt.id" class="col-4 inline">
				<NcCheckboxRadioSwitch :value="'' + opt.id" :checked.sync="localSelectionDefault" name="defaultValues" />
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
				{{ t('tables', 'You can set default values by marking the checkboxes next to the label fields.') }}
			</p>
		</div>
	</div>
</template>

<script>
import { NcCheckboxRadioSwitch, NcButton } from '@nextcloud/vue'
import DeleteOutline from 'vue-material-design-icons/DeleteOutline.vue'

export default {
	name: 'SelectionMultiForm',
	components: {
		NcCheckboxRadioSwitch,
		NcButton,
		DeleteOutline,
	},
	props: {
		selectionOptions: {
			type: Array,
			default: () => [],
		},
		selectionDefault: {
			type: String,
			default: '[]',
		},
	},
	computed: {
		localSelectionDefault: {
			get() {
				if (this.selectionDefault !== null && this.selectionDefault !== '') {
					return JSON.parse(this.selectionDefault)
				} else {
					return []
				}
			},
			set(value) {
				console.debug('try to set value', value)
				this.$emit('update:selectionDefault', JSON.stringify(value))
			},
		},
		localSelectionOptions: {
			get() {
				if (this.selectionOptions) {
					return this.selectionOptions
				}
				const options = [
					{
						id: 0,
						label: t('tables', 'First option'),
					},
					{
						id: 1,
						label: t('tables', 'Second option'),
					},
				]
				this.$emit('update:selectionOptions', options)
				return options
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
