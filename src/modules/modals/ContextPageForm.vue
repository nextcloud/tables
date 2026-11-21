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
				:helper-text="isStartPage ? t('tables', 'Leave empty to call it Home.') : ''"
				:maxlength="200"
				data-cy="contextPageTitle" />
			<IconField v-model="icon" :label="t('tables', 'Icon')" />
			<NcTextField v-if="page"
				:model-value="page.slug ?? ''"
				:label="t('tables', 'Slug')"
				:helper-text="t('tables', 'Used by imports and links to this page. It stays as it was set when the page was added.')"
				readonly
				data-cy="contextPageSlug" />
			<NcTextField v-else
				v-model="slug"
				:label="t('tables', 'Slug')"
				:maxlength="200"
				:error="slugError !== null"
				:aria-invalid="slugError !== null ? 'true' : undefined"
				:helper-text="slugError ?? t('tables', 'Used by imports and links to this page. It cannot be changed later. Leave empty to get one automatically.')"
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
import { isStartPage, pageSlugError } from '../grid/gridLayout.js'

/**
 * Asks for the title, icon, slug and description of a new grid page, or for the title,
 * icon and description of the page being edited, whose slug never changes.
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
			return this.page ? null : pageSlugError(this.slug.trim())
		},
		// The start page goes without a title, then it is called Home
		isStartPage() {
			return isStartPage(this.page)
		},
		canSubmit() {
			return (this.isStartPage || this.title.trim() !== '') && this.slugError === null
		},
	},
	watch: {
		showModal(open) {
			if (open) {
				this.title = this.page?.title ?? ''
				this.icon = this.page?.icon ?? null
				this.slug = ''
				this.description = this.page?.description ?? ''
			}
		},
	},
	methods: {
		/**
		 * Hands the fields to the parent. Fields left empty are sent empty, so they are removed.
		 * A slug goes only with a new page; left empty, the server gives one.
		 */
		submit() {
			if (!this.canSubmit || this.pending) {
				return
			}
			const data = { title: this.title.trim(), icon: this.icon ?? '', description: this.description.trim() }
			const slug = this.slug.trim()
			if (!this.page && slug !== '') {
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
