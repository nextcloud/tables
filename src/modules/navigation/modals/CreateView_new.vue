<template>
	<ViewModal />
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'
import { mapGetters } from 'vuex'
import ViewModal from '../../main/modals/ViewModal.vue'

export default {
	name: 'CreateView',
	components: {
		CreateView,
	},
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		baseView: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			view: {
				tableId: this.baseView.tableId,
				columns:
				
			},
		}
	},
	watch: {
		title() {
			if (this.title.length >= 200) {
				showError(t('tables', 'The title limit is reached with 200 characters. Please use a shorter title.'))
				this.title = this.title.slice(0, 199)
			}
		},
		showModal() {
			this.reset()
			this.loadEmoji()
		},
	},
	methods: {
		setIcon(icon) {
			this.icon = icon
		},
		loadEmoji() {
			const emojis = ['😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '🙃', '🫠', '😉', '😊', '😇']
			this.icon = emojis[~~(Math.random() * emojis.length)]
		},
		actionCancel() {
			this.reset()
			this.$emit('close')
			console.debug('Closed')
		},
		onToggle(columnId) {
			if (this.selectedColumns.includes(columnId)) {
				this.selectedColumns.splice(this.selectedColumns.indexOf(columnId), 1)
			} else {
				this.selectedColumns.push(columnId)
			}
		},
		async actionConfirm() {
			if (this.title === '') {
				showError(t('tables', 'Cannot create new view. Title is missing.'))
				this.errorTitle = true
			} else {
				this.localLoading = true
				const newViewId = await this.sendNewViewToBE()
				const success = await this.updateViewColumnsToBE(newViewId)
				this.localLoading = false
				if (success) {
					await this.$router.push('/view/' + newViewId)
					this.actionCancel()
				}
			}
		},
		async sendNewViewToBE() {
			const data = {
				tableId: this.activeTable.id,
				title: this.title,
				emoji: this.icon,
			}
			const res = await this.$store.dispatch('insertNewView', { data })
			if (res) {
				return res
			} else {
				showError(t('tables', 'Could not create new view'))
			}
		},
		async updateViewColumnsToBE(id) {
			const data = {
				data: { columns: JSON.stringify(this.selectedColumns) },
			}
			const res = await this.$store.dispatch('updateView', { id, data })
			if (res) {
				return res
			} else {
				showError(t('tables', 'Could not update view'))
			}
		},
		reset() {
			this.title = ''
			this.icon = ''
			this.errorTitle = false
			this.selectedColumns = this.columns.map(col => col.id)
			this.localLoading = false
		},
	},
}
</script>
<style lang="scss" scoped>

.padding-right {
	padding-right: calc(var(--default-grid-baseline) * 3);
}

</style>
