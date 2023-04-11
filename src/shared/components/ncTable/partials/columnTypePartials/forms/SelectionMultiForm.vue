<template>
	<div style="width: 100%">
		<div class="row">
			<div class="col-4 title space-T">
				{{ t('tables', 'Options') }}
			</div>
			<div v-for="opt in getSelectionOptions" :key="opt.id" class="col-4 inline">
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
				this.$emit('update:selectionDefault', JSON.stringify(value))
			},
		},
		getSelectionOptions() {
			// if we have or had options
			if (this.allOptions.length > 0) {
				return this.getAllNonDeletedOptions
			}

			// if running first time, load default options
			return this.loadDefaultOptions()
		},
		allOptions: {
			get() {
				return this.selectionOptions || []
			},
			set(value) {
				this.$emit('update:selectionOptions', [...value])
			},
		},
		getAllNonDeletedOptions() {
			return this.allOptions?.filter(item => {
				return !item.deleted
			}) || []
		},
	},
	methods: {
		loadDefaultOptions() {
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
		updateLabel(id, e) {
			const i = this.allOptions.findIndex((obj) => obj.id === id)
			const tmp = [...this.allOptions]
			tmp[i].label = e.target.value
			this.allOptions = tmp
		},
		addOption() {
			const nextId = this.getNextId()
			const options = [...this.allOptions]
			options.push({
				id: nextId,
				label: '',
			})
			this.allOptions = options
		},
		getNextId() {
			return Math.max(...this.allOptions.map(item => item.id)) + 1
		},
		deleteOption(id) {
			const i = this.allOptions.findIndex((obj) => obj.id === id)
			const tmpOptions = [...this.allOptions]
			tmpOptions[i].deleted = true
			this.allOptions = tmpOptions

			// if deleted option was default, remove default
			const index = this.localSelectionDefault.findIndex(item => parseInt(item) === id)
			if (index !== -1) {
				const defaults = this.localSelectionDefault.slice()
				defaults.splice(index, 1)
				this.localSelectionDefault = defaults
			}
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
