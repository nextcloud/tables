<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="showModal"
		:name="t('tables', 'Create table')"
		data-cy="createTableModal"
		size="normal"
		@closing="actionCancel">
		<div class="modal__content">
			<div class="row space-T">
				<div class="col-4 mandatory">
					{{ t('tables', 'Title') }}
				</div>
				<div class="col-4 content-emoji">
					<NcEmojiPicker class="content--emoji" :close-on-select="true" @select="setIcon">
						<NcButton type="tertiary"
							:aria-label="t('tables', 'Select emoji for table')"
							:title="t('tables', 'Select emoji')"
							@click.prevent>
							{{ icon }}
						</NcButton>
					</NcEmojiPicker>
					<input ref="titleInput"
						v-model="title"
						:class="{missing: errorTitle}"
						type="text"
						:placeholder="t('tables', 'Title of the new table')"
						@input="titleChangedManually">
				</div>
			</div>
			<div class="row">
				<div class="col-4 space-T mandatory">
					{{ t('tables', 'Description') }}
				</div>
				<div class="col-4">
					<TableDescription :description.sync="description" :read-only="false" />
				</div>
			</div>
			<div class="row space-T">
				<div class="col-2 block space-R space-B">
					<NcTile
						:title="t('tables', '🔧 Custom table')"
						:body="t('tables', 'Custom table from scratch.')"
						:active="templateChoice === 'custom'"
						:tabbable="true"
						@set-template="setTemplate('custom')" />
				</div>
				<div class="col-2 block space-R space-B">
					<NcTile
						:title="t('tables', '📄 Import table')"
						:body="t('tables', 'Import table from file.')"
						:active="templateChoice === 'import'"
						:tabbable="true"
						@set-template="setTemplate('import')" />
				</div>
				<div v-for="template in templates" :key="template.name" class="col-2 block space-R space-B">
					<NcTile
						:title="template.icon + ' ' + template.title"
						:body="template.description"
						:active="templateChoice === template.name"
						:tabbable="true"
						@set-template="setTemplate(template.name)" />
				</div>
				<div class="col-2 block space-R space-B">
					<NcTile
						:title="t('tables', '📄 Import Scheme')"
						:body="t('tables', 'Import Scheme from file.')"
						:active="templateChoice === 'scheme'"
						:tabbable="true"
						@set-template="setTemplate('scheme')" />
				</div>
			</div>
			<div class="row space-R">
				<div class="fix-col-4 end">
					<NcButton type="primary" :aria-label="t('tables', 'Create table')" data-cy="createTableSubmitBtn" @click="submit">
						{{ t('tables', 'Create table') }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcDialog>
</template>

<script>
import { NcDialog, NcEmojiPicker, NcButton } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import NcTile from '../../shared/components/ncTile/NcTile.vue'
import displayError from '../../shared/utils/displayError.js'
import TableDescription from '../../modules/main/sections/TableDescription.vue'
import { emit } from '@nextcloud/event-bus'
import { mapState, mapActions } from 'pinia'
import { useTablesStore } from '../../store/store.js'

export default {
	name: 'CreateTable',
	components: {
		NcDialog,
		NcEmojiPicker,
		NcButton,
		NcTile,
		TableDescription,
	},
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			title: '',
			icon: '',
			description: '',
			errorTitle: false,
			templateChoice: 'custom',
			customIconChosen: false,
			customTitleChosen: false,
		}
	},
	computed: {
		...mapState(useTablesStore, ['templates']),
	},
	watch: {
		title() {
			if (this.title.length >= 200) {
				showError(t('tables', 'The title character limit is 200 characters. Please use a shorter title.'))
				this.title = this.title.slice(0, 199)
			}
		},
		showModal() {
			// every time when the modal opens chose a new emoji
			this.loadEmoji()
			this.$nextTick(() => this.$refs?.titleInput?.focus())
		},
	},
	methods: {
		...mapActions(useTablesStore, ['insertNewTable']),
		titleChangedManually() {
			this.customTitleChosen = true
		},
		setIcon(icon) {
			this.icon = icon
			this.customIconChosen = true
		},
		setTemplate(name) {
			this.templateChoice = name

			if (!this.customIconChosen) {
				if (name === 'custom') {
					this.icon = '🔧'
				} else if (name === 'import') {
					this.icon = '📄'
				} else {
					const templateObject = this.templates?.find(item => item.name === name) || ''
					this.icon = templateObject?.icon
				}
			}

			if (!this.customTitleChosen) {
				if (name === 'custom' || name === 'import') {
					this.title = ''
				} else {
					const templateObject = this.templates?.find(item => item.name === name) || ''
					this.title = templateObject?.title || ''
				}
			}
		},
		loadEmoji() {
			const emojis = ['😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '🙃', '🫠', '😉', '😊', '😇']
			this.icon = emojis[~~(Math.random() * emojis.length)]
		},
		actionCancel() {
			this.reset()
			this.$emit('close')
		},
		async submit() {
			if (this.templateChoice === 'scheme') {
				emit('tables:modal:scheme', this.title)
				this.actionCancel()
				return
			}
			if (this.title === '') {
				showError(t('tables', 'Cannot create new table. Title is missing.'))
				this.errorTitle = true
			} else {
				const newTableId = await this.sendNewTableToBE(this.templateChoice)
				if (newTableId) {
					await this.$router.push('/table/' + newTableId)
					if (this.templateChoice === 'import') {
						emit('tables:modal:import', { element: { tableId: newTableId, id: newTableId }, isView: false })
					}
					this.actionCancel()
				}
			}
		},
		async sendNewTableToBE(template) {
			const data = {
				title: this.title,
				description: this.description,
				emoji: this.icon,
				template,
			}
			const res = await this.insertNewTable({ data })
			if (res) {
				return res.id
			} else {
				showError(t('tables', 'Could not create new table'))
			}
		},
		reset() {
			this.title = ''
			this.errorTitle = false
			this.templateChoice = 'custom'
			this.icon = ''
			this.customIconChosen = false
			this.customTitleChosen = false
		},
		async loadTemplatesFromBE() {
			try {
				const res = await axios.get(generateUrl('/apps/tables/table/templates'))
				this.templates = res.data
			} catch (e) {
				displayError(e, t('tables', 'Could not load templates.'))
			}
		},
	},
}
</script>
<style lang="scss" scoped>

:deep(.element-description) {
	padding-inline: 0 !important;
	max-width: 100%;
}

.modal__content {
	padding-inline-end: 0 !important;
	.content-emoji {
		display: inline-flex;
		align-items: center;
	}
}

</style>
