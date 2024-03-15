<template>
	<div>
		<ElementDescription :active-element="table" :view-setting.sync="localViewSetting" />
		<div id="description-editor" ref="textDiv" />
		<Dashboard v-if="hasViews"
			:table="table"
			@create-column="$emit('create-column')"
			@import="$emit('import')"
			@toggle-share="$emit('toggle-share')"
			@show-integration="$emit('show-integration')"
			@create-view="createView" />
		<DataTable :table="table" :columns="columns" :rows="rows" :view-setting.sync="localViewSetting"
			@create-column="$emit('create-column')"
			@import="$emit('import')"
			@download-csv="$emit('download-csv')"
			@toggle-share="$emit('toggle-share')"
			@show-integration="$emit('show-integration')"
			@create-view="createView" />
	</div>
</template>

<script>
import ElementDescription from './ElementDescription.vue'
import Dashboard from './Dashboard.vue'
import DataTable from './DataTable.vue'
import { mapState } from 'vuex'

import { emit } from '@nextcloud/event-bus'

export default {
	components: {
		ElementDescription,
		Dashboard,
		DataTable,
	},

	props: {
		table: {
			type: Object,
			default: null,
		},
		columns: {
			type: Array,
			default: null,
		},
		rows: {
			type: Array,
			default: null,
		},
		viewSetting: {
			type: Object,
			default: null,
		},
	},

	data() {
		return {
			localViewSetting: this.viewSetting,
			description: '',
		}
	},
	computed: {
		...mapState(['views']),
		hasViews() {
			return this.views.some(v => v.tableId === this.table.id)
		},
	},
	watch: {
		localViewSetting() {
			this.$emit('update:viewSetting', this.localViewSetting)
		},
		viewSetting() {
			this.localViewSetting = this.viewSetting
		},
	},
	mounted() {
		this.setupEditor()
	},
	async beforeDestroy() {
		await this.destroyEditor()
	},
	methods: {
		createView() {
			emit('tables:view:create', { tableId: this.table.id, viewSetting: this.viewSetting.length > 0 ? this.viewSetting : this.localViewSetting })
		},
		async setupEditor() {
			await this.destroyEditor()
			this.descriptionLastEdited = 0
			this.description = this.table.description
			this.editor = await window.OCA.Text.createEditor({
				el: this.$refs.textDiv,
				content: this.table.description,
				onUpdate: ({ markdown }) => {
					if (this.description === markdown) {
						this.descriptionLastEdit = 0
						return
					}
					this.description = markdown
					this.updateDescription()
				},
			})
		},
		async saveDescription() {
			if (this.descriptionLastEdited !== 0) return
			this.descriptionSaving = true
			await this.$store.dispatch('updateTableProperty', { id: this.table.id, data: { description: this.description }, property: 'description' })
			this.descriptionLastEdit = 0
			this.descriptionSaving = false
		},
		updateDescription() {
			this.descriptionLastEdit = Date.now()
			clearTimeout(this.descriptionSaveTimeout)
			this.descriptionSaveTimeout = setTimeout(async () => {
				await this.saveDescription()
			}, 2500)
		},
		async destroyEditor() {
			await this.saveDescription()
			this?.editor?.destroy()
		},
	},
}
</script>
