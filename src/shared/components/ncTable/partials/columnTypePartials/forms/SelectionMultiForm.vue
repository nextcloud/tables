<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div style="width: 100%">
		<div class="row">
			<div class="col-4 title">
				{{ t('tables', 'Options') }}
			</div>
			<div v-for="opt in mutableColumn.selectionOptions" :key="opt.id" class="col-4 inline" data-cy="selectionOption">
				<NcCheckboxRadioSwitch :value="'' + opt.id" :checked.sync="mutableColumn.selectionDefault" name="defaultValues" />
				<input :value="opt.label" data-cy="selectionOptionLabel" @input="updateLabel(opt.id, $event)">
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
import DeleteOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'SelectionMultiForm',
	components: {
		NcCheckboxRadioSwitch,
		NcButton,
		DeleteOutline,
	},
	props: {
		column: {
			type: Object,
			default: null,
		},
		canSave: {
			type: Boolean,
			default: true,
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
	created() {
		if (!this.mutableColumn.selectionOptions || this.mutableColumn.selectionOptions?.length === 0) {
			this.mutableColumn.selectionOptions = this.loadDefaultOptions()
		}
		if (!this.mutableColumn.selectionDefault) {
			this.mutableColumn.selectionDefault = []
		} else if (typeof this.mutableColumn.selectionDefault === 'string') {
			this.mutableColumn.selectionDefault = JSON.parse(this.mutableColumn.selectionDefault)
		}
		if (!Array.isArray(this.mutableColumn.selectionDefault)) {
			this.mutableColumn.selectionDefault = []
		}
	},
	methods: {
		t,
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
			return options
		},
		updateLabel(id, e) {
			const i = this.mutableColumn.selectionOptions.findIndex((obj) => obj.id === id)
			const tmp = [...this.mutableColumn.selectionOptions]
			tmp[i].label = e.target.value
			this.mutableColumn.selectionOptions = tmp
		},
		addOption() {
			const nextId = this.getNextId()
			const options = [...this.mutableColumn.selectionOptions]
			options.push({
				id: nextId,
				label: '',
			})
			this.mutableColumn.selectionOptions = options
		},
		getNextId() {
			if (this.mutableColumn.selectionOptions.length > 0) {
				return Math.max(...this.mutableColumn.selectionOptions.map(item => item.id)) + 1
			} else {
				return 0
			}
		},
		deleteOption(id) {
			this.mutableColumn.selectionOptions = this.mutableColumn.selectionOptions.filter(opt => opt.id !== id)

			// if deleted option was default, remove default
			const index = this.mutableColumn.selectionDefault.findIndex(item => parseInt(item) === id)
			if (index !== -1) {
				const defaults = this.mutableColumn.selectionDefault.slice()
				defaults.splice(index, 1)
				this.mutableColumn.selectionDefault = defaults
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
	margin-inline-start: calc(var(--default-grid-baseline) * 1);
}

.col-4.inline {
	margin-inline-start: calc(var(--default-grid-baseline) * 3);
}

</style>
