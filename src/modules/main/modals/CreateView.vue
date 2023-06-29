<template>
	<NcModal v-if="showModal" @close="actionCancel">
		<div class="modal__content">
			<div class="row">
				<div class="col-4">
					<h2 style="padding: 0">
						{{ t('tables', 'Create view') }}
					</h2>
				</div>
			</div>

			<div class="row space-T">
				<div class="col-4 mandatory">
					{{ t('tables', 'Title') }}
				</div>
				<div class="col-4" style="display: inline-flex;">
					<NcEmojiPicker :close-on-select="true" @select="setIcon">
						<NcButton type="tertiary"
							:aria-label="t('tables', 'Select emoji for view')"
							:title="t('tables', 'Select emoji')"
							@click.prevent>
							{{ icon }}
						</NcButton>
					</NcEmojiPicker>
					<input v-model="title"
						:class="{missing: errorTitle}"
						type="text"
						:placeholder="t('tables', 'Title of the new view')">
				</div>
			</div>
			<div class="col-4 mandatory">
				{{ t('tables', 'Columns to be displayed') }}
			</div>
			<div v-for="column in columns" :key="column.id" style="display: flex; align-items: center;">
				<NcCheckboxRadioSwitch
					:checked="selectedColumns.includes(column.id)"
					style="padding-right: 10px;"
					@update:checked="onToggle(column.id)" />
				{{ column.title }}
			</div>
			<div class="row">
				<div class="fix-col-4 space-T end">
					<button v-if="!localLoading" class="primary" :aria-label="t('tables', 'Save View')" @click="actionConfirm()">
						{{ t('tables', 'Save') }}
					</button>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcEmojiPicker, NcButton, NcCheckboxRadioSwitch } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'
import { mapGetters } from 'vuex'

export default {
	name: 'CreateView',
	components: {
		NcModal,
		NcEmojiPicker,
		NcButton,
		NcCheckboxRadioSwitch,
	},
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		columns: {
			type: Array,
			default: null,
		},
	},
	data() {
		return {
			title: '',
			icon: '',
			errorTitle: false,
			selectedColumns: this.columns.map(col => col.id),
			localLoading: false,
		}
	},
	computed: {
		...mapGetters(['activeTable']),
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
