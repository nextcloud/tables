<template>
	<div class="row space-R">
		<!-- title -->
		<div class="fix-col-4 mandatory title space-T" :class="{error: titleMissingError}">
			{{ t('tables', 'Title') }}
		</div>
		<div class="fix-col-4" :class="{error: titleMissingError}">
			<input v-model="localTitle" :placeholder="t('tables', 'Enter a column title')">
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
		<div class="fix-col-4 title space-T">
			{{ activeView.isBaseView? t('tables', 'Add column to views') : t('tables', 'Add column to other views') }}
		</div>
		<div class="fix-col-4">
			<NcSelect
				v-model="localSelectedViews"
				:multiple="true"
				:options="viewsForTable"
				:get-option-key="(option) => option.id"
				:placeholder="t('tables', 'Column')"
				label="title">
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
		orderWeight: {
			type: Number,
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
	},
	computed: {
		...mapGetters(['activeView']),
		...mapState(['views']),
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
		localSelectedViews: {
			get() { return this.selectedViews },
			set(selectedViews) {
				console.debug(selectedViews)
				this.$emit('update:selectedViews', selectedViews)
			},
		},
		viewsForTable() {
			return this.views.filter(view => view.tableId === this.activeView.tableId && view !== this.activeView && !view.isBaseView).filter(view => !this.localSelectedViews.includes(view))
		},
	},

	mounted() {
		if (this.activeView.isBaseView) {
			this.localSelectedViews = this.viewsForTable
		} else {
			this.localSelectedViews = []
		}
	},
}
</script>
