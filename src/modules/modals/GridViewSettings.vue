<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="showModal"
		:name="view ? t('tables', 'Edit grid view') : t('tables', 'Create grid view')"
		size="normal"
		data-cy="gridViewSettingsModal"
		@closing="actionCancel">
		<div class="modal__content">
			<div class="row space-T">
				<div class="col-4 mandatory">
					{{ t('tables', 'Title') }}
				</div>
				<div class="col-4 title-row">
					<NcEmojiPicker :close-on-select="true" @select="emoji => icon = emoji">
						<NcButton variant="tertiary"
							:aria-label="t('tables', 'Select emoji for view')"
							:title="t('tables', 'Select emoji')"
							@click.prevent>
							{{ icon }}
						</NcButton>
					</NcEmojiPicker>
					<input v-model="title"
						:class="{ missing: errorTitle }"
						type="text"
						data-cy="gridViewTitle"
						:placeholder="t('tables', 'Title of the grid view')">
				</div>
			</div>
			<div class="row space-T">
				<div class="col-4">
					{{ t('tables', 'Description') }}
				</div>
				<input v-model="description" type="text" data-cy="gridViewDescription"
					:placeholder="t('tables', 'What this page is for')">
			</div>
			<div class="row space-T">
				<div class="col-4">
					{{ t('tables', 'Slug') }}
				</div>
				<input v-model="slug" type="text" data-cy="gridViewSlug"
					:class="{ missing: errorSlug }"
					:placeholder="t('tables', 'Optional, e.g. home. Lowercase letters, numbers and hyphens.')">
			</div>
			<div class="row space-T">
				<div class="fix-col-4 end">
					<NcButton variant="primary" data-cy="gridViewSubmit" @click="submit">
						{{ view ? t('tables', 'Save') : t('tables', 'Create grid view') }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcDialog>
</template>

<script>
import { NcButton, NcDialog, NcEmojiPicker } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import { mapActions } from 'pinia'
import { useTablesStore } from '../../store/store.js'

const SLUG_PATTERN = /^[a-z0-9][a-z0-9-]*$/

/**
 * Creates a grid view, or edits the title, description and slug of one.
 */
export default {
	name: 'GridViewSettings',
	components: {
		NcButton,
		NcDialog,
		NcEmojiPicker,
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
		// passed through to the created event so an application can add the view to its menu
		contextId: {
			type: Number,
			default: null,
		},
	},
	emits: ['close', 'created'],
	data() {
		return {
			title: '',
			description: '',
			slug: '',
			icon: '',
			errorTitle: false,
			errorSlug: false,
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
		reset() {
			this.title = this.view?.title ?? ''
			this.description = this.view?.description ?? ''
			this.slug = this.view?.slug ?? ''
			this.icon = this.view?.emoji || '🧩'
			this.errorTitle = false
			this.errorSlug = false
			this.saving = false
		},
		actionCancel() {
			this.$emit('close')
		},
		async submit() {
			this.errorTitle = this.title.trim() === ''
			this.errorSlug = this.slug !== '' && !SLUG_PATTERN.test(this.slug)
			if (this.errorTitle) {
				showError(t('tables', 'Cannot save the view. Title is missing.'))
				return
			}
			if (this.errorSlug) {
				showError(t('tables', 'A slug may only contain lowercase letters, numbers and hyphens.'))
				return
			}
			this.saving = true
			if (this.view) {
				const success = await this.updateView({
					id: this.view.id,
					data: { data: { title: this.title.trim(), description: this.description, emoji: this.icon, slug: this.slug } },
				})
				this.saving = false
				if (success) {
					this.$emit('close')
				}
				return
			}
			const created = await this.insertStandaloneView({
				data: { title: this.title.trim(), description: this.description, emoji: this.icon, type: 'grid', slug: this.slug || null },
			})
			this.saving = false
			if (created) {
				this.$emit('created', { view: created, contextId: this.contextId })
				this.$emit('close')
				if (!this.contextId) {
					await this.$router.push('/view/' + created.id).catch(err => err)
				}
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.modal__content {
	padding-inline-end: 0 !important;

	.title-row {
		display: inline-flex;
		align-items: center;
		width: 100%;

		input {
			flex: 1;
		}
	}

	input[type="text"] {
		width: 100%;
	}
}
</style>
