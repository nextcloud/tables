<template>
	<NcModal v-if="showModal"
		size="normal"
		@close="actionCancel">
		<div class="modal__content">
			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Edit table') }}</h2>
				</div>
			</div>
			<div class="row">
				<div class="col-4 mandatory">
					{{ t('tables', 'Title') }}
				</div>
				<div class="col-3 inline">
					<NcEmojiPicker :close-on-select="true" @select="emoji => icon = emoji">
						<NcButton type="tertiary"
							:aria-label="t('tables', 'Select emoji for table')"
							:title="t('tables', 'Select emoji')"
							@click.prevent>
							{{ icon ? icon : '...' }}
						</NcButton>
					</NcEmojiPicker>
					<input v-model="title"
						:class="{missing: errorTitle}"
						type="text"
						:placeholder="t('tables', 'Title of the new table')">
				</div>
			</div>
			<div class="row">
				<div class="fix-col-4 space-T justify-between">
					<NcButton v-if="!prepareDeleteTable" type="error" @click="prepareDeleteTable = true">
						{{ t('tables', 'Delete') }}
					</NcButton>
					<NcButton v-if="prepareDeleteTable"
						:wide="true"
						type="error"
						@click="actionDeleteTable">
						{{ t('tables', 'I really want to delete this table!') }}
					</NcButton>
					<NcButton type="primary" @click="submit">
						{{ t('tables', 'Save') }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcEmojiPicker, NcButton } from '@nextcloud/vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'
import { mapGetters } from 'vuex'

export default {
	name: 'EditTable',
	components: {
		NcModal,
		NcEmojiPicker,
		NcButton,
	},
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		table: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			title: '',
			icon: '',
			errorTitle: false,
			prepareDeleteTable: false,
		}
	},
	computed: {
		...mapGetters(['activeTable']),
	},
	watch: {
		title() {
			if (this.title && this.title.length >= 200) {
				showError(t('tables', 'The title limit is reached with 200 characters. Please use a shorter title.'))
				this.title = this.title.slice(0, 199)
			}
		},
		table() {
			this.reset()
		},
	},
	methods: {
		actionCancel() {
			this.reset()
			this.$emit('close')
		},
		async submit() {
			if (this.title === '') {
				showError(t('tables', 'Cannot update table. Title is missing.'))
				this.errorTitle = true
			} else {
				const res = await this.$store.dispatch('updateTable', { id: this.table.id, data: { title: this.title, emoji: this.icon } })
				if (res) {
					showSuccess(t('tables', 'Updated table "{emoji}{table}".', { emoji: this.icon ? this.icon + ' ' : '', table: this.title }))
					this.actionCancel()
				}
			}
		},
		reset() {
			if (this.table) {
				this.title = this.table.title
				this.icon = this.table.emoji
				this.errorTitle = false
				this.templateChoice = 'custom'
				this.prepareDeleteTable = false
			}
		},
		async actionDeleteTable() {
			const deleteId = this.table.id
			const activeTableId = this.activeTable.id
			this.prepareDeleteTable = false
			const res = await this.$store.dispatch('removeTable', { tableId: this.table.id })
			if (res) {
				showSuccess(t('tables', 'Table "{emoji}{table}" removed.', { emoji: this.icon ? this.icon + ' ' : '', table: this.title }))

				// if the actual table was deleted, go to startpage
				if (deleteId === activeTableId) {
					await this.$router.push('/').catch(err => err)
				}

				this.actionCancel()
			}

		},
	},
}
</script>
