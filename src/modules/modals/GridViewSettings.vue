<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="showModal"
		:name="view ? t('tables', 'Edit grid view') : t('tables', 'Create grid view')"
		size="normal"
		data-cy="gridViewSettingsModal"
		@closing="$emit('close')">
		<div class="grid-view-settings">
			<div class="grid-view-settings__title-row">
				<NcEmojiPicker :close-on-select="true" @select="emoji => icon = emoji">
					<NcButton variant="tertiary"
						:aria-label="t('tables', 'Select emoji for view')"
						:title="t('tables', 'Select emoji')"
						@click.prevent>
						{{ icon }}
					</NcButton>
				</NcEmojiPicker>
				<NcTextField v-model="title"
					class="grid-view-settings__field"
					:label="t('tables', 'Title')"
					:error="errorTitle"
					:helper-text="errorTitle ? t('tables', 'A grid view needs a title') : ''"
					required
					data-cy="gridViewTitle" />
			</div>
			<NcTextField v-model="description"
				class="grid-view-settings__field"
				:label="t('tables', 'Description')"
				:placeholder="t('tables', 'What this page is for')"
				data-cy="gridViewDescription" />
		</div>
		<template #actions>
			<NcButton variant="primary" :disabled="saving" data-cy="gridViewSubmit" @click="submit">
				{{ view ? t('tables', 'Save') : t('tables', 'Create grid view') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcDialog, NcEmojiPicker, NcTextField } from '@nextcloud/vue'
import { mapActions } from 'pinia'
import { useTablesStore } from '../../store/store.js'

/**
 * Creates a grid view, or edits the title, emoji and description of one. A grid view is
 * named by its id, so it has no technical name to set here.
 */
export default {
	name: 'GridViewSettings',
	components: {
		NcButton,
		NcDialog,
		NcEmojiPicker,
		NcTextField,
	},
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		view: {
			type: Object,
			default: null,
		},
	},
	emits: ['close'],
	data() {
		return {
			title: '',
			description: '',
			icon: '',
			errorTitle: false,
			saving: false,
		}
	},
	watch: {
		showModal(open) {
			if (open) {
				this.reset()
			}
		},
	},
	methods: {
		...mapActions(useTablesStore, ['insertStandaloneView', 'updateView']),
		/**
		 * Fills the form with the view being edited, or empty for a new one.
		 */
		reset() {
			this.title = this.view?.title ?? ''
			this.description = this.view?.description ?? ''
			this.icon = this.view?.emoji || '🧩'
			this.errorTitle = false
			this.saving = false
		},
		/**
		 * Saves the view, or creates it and opens it.
		 */
		async submit() {
			this.errorTitle = this.title.trim() === ''
			if (this.errorTitle || this.saving) {
				return
			}
			this.saving = true
			const data = { title: this.title.trim(), description: this.description, emoji: this.icon }
			if (this.view) {
				const success = await this.updateView({ id: this.view.id, data: { data } })
				this.saving = false
				if (success) {
					this.$emit('close')
				}
				return
			}
			const created = await this.insertStandaloneView({ data })
			this.saving = false
			if (created) {
				this.$emit('close')
				await this.$router.push('/view/' + created.id).catch(err => err)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.grid-view-settings {
	display: flex;
	flex-direction: column;
	gap: calc(2 * var(--default-grid-baseline));

	&__title-row {
		display: flex;
		align-items: flex-end;
		gap: var(--default-grid-baseline);
	}

	&__field {
		flex: 1;
	}
}
</style>
