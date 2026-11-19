<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="showModal"
		:name="page ? t('tables', 'Edit page details') : t('tables', 'Add page')"
		size="normal"
		is-form
		data-cy="contextPageForm"
		@closing="$emit('close')"
		@submit="submit">
		<div class="context-page-form">
			<NcTextField v-model="title"
				:label="t('tables', 'Title')"
				:maxlength="200"
				data-cy="contextPageTitle" />
			<IconField v-model="icon" :label="t('tables', 'Icon')" />
			<NcTextField v-model="slug"
				:label="t('tables', 'Slug')"
				:maxlength="200"
				:error="slugError !== null"
				:aria-invalid="slugError !== null ? 'true' : undefined"
				:helper-text="slugError ?? t('tables', 'Used by imports and links to this page. Leave empty to get one automatically.')"
				data-cy="contextPageSlug" />
			<NcTextArea v-model="description"
				:label="t('tables', 'Description')"
				:maxlength="2000"
				resize="vertical"
				rows="3"
				data-cy="contextPageDescription" />
		</div>
		<template #actions>
			<NcButton variant="primary"
				type="submit"
				:disabled="!canSubmit || pending"
				data-cy="contextPageSubmit">
				{{ page ? t('tables', 'Save') : t('tables', 'Add page') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcDialog, NcTextArea, NcTextField } from '@nextcloud/vue'
import IconField from '../../shared/components/ncIconPicker/IconField.vue'
import { pageSlugError } from '../grid/gridLayout.js'

/**
 * Asks for the title, icon, slug and description of a new grid page, or of the
 * page being edited.
 */
export default {
	name: 'ContextPageForm',
	components: {
		IconField,
		NcButton,
		NcDialog,
		NcTextArea,
		NcTextField,
	},
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		// The page to edit; null for a new page
		page: {
			type: Object,
			default: null,
		},
		// The page is being added or saved
		pending: {
			type: Boolean,
			default: false,
		},
	},
	emits: ['close', 'submit'],
	data() {
		return {
			title: '',
			icon: null,
			slug: '',
			description: '',
		}
	},
	computed: {
		slugError() {
			// A page keeps the name it has, also one the server gave it
			if (this.page && this.slug === this.page.slug) {
				return null
			}
			return pageSlugError(this.slug.trim())
		},
		canSubmit() {
			return this.title.trim() !== '' && this.slugError === null
		},
	},
	watch: {
		showModal(open) {
			if (open) {
				this.title = this.page?.title ?? ''
				this.icon = this.page?.icon ?? null
				this.slug = this.page?.slug ?? ''
				this.description = this.page?.description ?? ''
			}
		},
	},
	methods: {
		/**
		 * Hands the fields to the parent. Fields left empty are sent empty, so they are removed;
		 * an empty slug is left out, so the server keeps or gives one.
		 */
		submit() {
			if (!this.canSubmit || this.pending) {
				return
			}
			const data = { title: this.title.trim(), icon: this.icon ?? '', description: this.description.trim() }
			const slug = this.slug.trim()
			if (slug !== '' && slug !== this.page?.slug) {
				data.slug = slug
			}
			this.$emit('submit', data)
		},
	},
}
</script>

<style lang="scss" scoped>
.context-page-form {
	display: flex;
	flex-direction: column;
	gap: calc(2 * var(--default-grid-baseline));
}
</style>
