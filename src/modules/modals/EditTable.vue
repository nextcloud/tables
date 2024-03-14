<template>
	<NcModal v-if="showModal"
		size="normal"
		@close="actionCancel">
		<div class="modal__content" data-cy="editTableModal">
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
				<div class="col-4 mandatory space-T">
					{{ t('tables', 'Owner') }}
				</div>
				<div class="col-3 inline space-T-small">
					<NcUserBubble
						:margin="4"
						:size="30"
						:display-name="localTable.ownerDisplayName"
						:user="localTable.owner" />
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
						<div class="right-additional-button">
							<NcButton v-if="ownsTable(localTable)" @click="actionTransfer">
								{{ t('tables', 'Change owner') }}
							</NcButton>
							<NcButton type="primary" @click="submit">
								{{ t('tables', 'Save') }}
							</NcButton>
						</div>
					</div>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcEmojiPicker, NcButton, NcUserBubble } from '@nextcloud/vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import { mapGetters } from 'vuex'
import permissionsMixin from '../../shared/components/ncTable/mixins/permissionsMixin.js'
import { emit } from '@nextcloud/event-bus'

export default {
	name: 'EditTable',
	components: {
		NcModal,
		NcEmojiPicker,
		NcButton,
		NcUserBubble,
	},
	mixins: [permissionsMixin],
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		tableId: {
			type: Number,
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
		...mapGetters(['getTable', 'activeTable']),
		localTable() {
			return this.getTable(this.tableId)
		},
	},
	watch: {
		title() {
			if (this.title && this.title.length >= 200) {
				showError(t('tables', 'The title limit is reached with 200 characters. Please use a shorter title.'))
				this.title = this.title.slice(0, 199)
			}
		},
		tableId() {
			if (this.tableId) {
				const table = this.getTable(this.tableId)
				this.title = table.title
				this.icon = table.emoji
			}
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
				const res = await this.$store.dispatch('updateTable', { id: this.tableId, data: { title: this.title, emoji: this.icon } })
				if (res) {
					showSuccess(t('tables', 'Updated table "{emoji}{table}".', { emoji: this.icon ? this.icon + ' ' : '', table: this.title }))
					this.actionCancel()
				}
			}
		},
		reset() {
			this.title = ''
			this.errorTitle = false
			this.templateChoice = 'custom'
			this.icon = ''
			this.prepareDeleteTable = false
		},
		async actionDeleteTable() {
			const deleteId = this.tableId
			const activeTableId = this.activeTable.id
			this.prepareDeleteTable = false
			const res = await this.$store.dispatch('removeTable', { tableId: this.tableId })
			if (res) {
				showSuccess(t('tables', 'Table "{emoji}{table}" removed.', { emoji: this.icon ? this.icon + ' ' : '', table: this.title }))

				// if the actual table was deleted, go to startpage
				if (deleteId === activeTableId) {
					await this.$router.push('/').catch(err => err)
				}

				this.actionCancel()
			}

		},
		actionTransfer() {
			emit('tables:table:edit', null)
			emit('tables:table:transfer', this.localTable)
		},
	},
}
</script>
<style lang="scss" scoped>

.right-additional-button {
	display: inline-flex;
}

.right-additional-button > button {
	margin-left: calc(var(--default-grid-baseline) * 3);
}

</style>
