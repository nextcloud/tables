<template>
	<div class="row space-R">
		<!-- title -->
		<div class="fix-col-4 mandatory title space-T" :class="{error: titleMissingError}">
			{{ t('tables', 'Title') }}
		</div>
		<div class="fix-col-4" :class="{error: titleMissingError}">
			<input v-model="localTitle" data-cy="columnTypeFormInput" :placeholder="t('tables', 'Enter a column title')">
		</div>

		<!-- description -->
		<div class="fix-col-4 title space-T">
			{{ t('tables', 'Description') }}
		</div>
		<div class="fix-col-4">
			<textarea v-model="localDescription" />
		</div>

		<!-- mandatory -->
		<div class="fix-col-4 title space-T">
			{{ t('tables', 'Mandatory') }}
		</div>
		<div class="fix-col-4">
			<NcCheckboxRadioSwitch type="switch" :checked.sync="localMandatory" />
		</div>

		<!-- add to views -->
		<div v-if="!editColumn && views.length > 0" class="fix-col-4 title space-T">
			{{ t('tables', 'Add column to other views') }}
		</div>
		<div v-if="!editColumn && views.length > 0" class="fix-col-4">
			<NcSelect
				v-model="localSelectedViews"
				:multiple="true"
				:options="viewsForTable"
				:get-option-key="(option) => option.id"
				:placeholder="t('tables', 'Column')"
				label="title"
				:aria-label-combobox="t('tables', 'Column')">
				<template #option="props">
					<div>
						{{ props.emoji }}
						{{ props.title }}
					</div>
				</template>
				<template #selected-option="props">
					<div>
						{{ props.emoji }}
						{{ props.title }}
					</div>
				</template>
			</NcSelect>
		</div>
	</div>
</template>

<script>
import { NcCheckboxRadioSwitch, NcSelect } from '@nextcloud/vue'
import { mapGetters, mapState } from 'vuex'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'MainForm',
	components: {
		NcCheckboxRadioSwitch,
		NcSelect,
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
		selectedViews: {
			type: Array,
			default: null,
		},
		titleMissingError: {
			type: Boolean,
			default: false,
		},
		editColumn: {
			type: Boolean,
			default: false,
		},
	},
	computed: {
		...mapState(['views']),
		...mapGetters(['activeElement', 'isView']),
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
		localSelectedViews: {
			get() { return this.selectedViews },
			set(selectedViews) {
				this.$emit('update:selectedViews', selectedViews)
			},
		},
		viewsForTable() {
			if (this.isView) {
				return this.views.filter(view => view.tableId === this.activeElement.tableId && view !== this.activeElement).filter(view => !this.localSelectedViews.includes(view))
			}
			return this.views.filter(view => view.tableId === this.activeElement?.id).filter(view => !this.localSelectedViews.includes(view))
		},
	},

	mounted() {
		if (this.editColumn) return
		if (!this.isView) {
			this.localSelectedViews = this.viewsForTable
		} else {
			this.localSelectedViews = []
		}
	},
	methods: {
		t,
	},
}
</script>
