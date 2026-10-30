<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="showModal"
		:name="page ? t('tables', 'Edit page details') : t('tables', 'Add page')"
		size="normal"
		data-cy="contextPageForm"
		@closing="$emit('close')">
		<form class="context-page-form" @submit.prevent="submit">
			<NcTextField v-model="title"
				:label="t('tables', 'Title')"
				:maxlength="200"
				data-cy="contextPageTitle" />
			<IconField v-model="icon" :label="t('tables', 'Icon')" />
			<NcTextField v-model="technicalName"
				:label="t('tables', 'Technical name')"
				:maxlength="200"
				:error="technicalNameError !== null"
				:helper-text="technicalNameError ?? t('tables', 'Used by imports and links to this page. Leave empty to get one automatically.')"
				data-cy="contextPageTechnicalName" />
			<NcTextArea v-model="description"
				:label="t('tables', 'Description')"
				:maxlength="2000"
				resize="vertical"
				rows="3"
				data-cy="contextPageDescription" />
		</form>
		<template #actions>
			<NcButton variant="primary" :disabled="!canSubmit" data-cy="contextPageSubmit" @click="submit">
				{{ page ? t('tables', 'Save') : t('tables', 'Add page') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcDialog, NcTextArea, NcTextField } from '@nextcloud/vue'
import IconField from '../../shared/components/ncIconPicker/IconField.vue'
import { pageTechnicalNameError } from '../grid/gridLayout.js'

/**
 * Asks for the title, icon, technical name and description of a new grid page, or of the
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
	},
	emits: ['close', 'submit'],
	data() {
		return {
			title: '',
			icon: null,
			technicalName: '',
			description: '',
		}
	},
	computed: {
		technicalNameError() {
			// A page keeps the name it has, also one the server gave it
			if (this.page && this.technicalName === this.page.technicalName) {
				return null
			}
			return pageTechnicalNameError(this.technicalName.trim())
		},
		canSubmit() {
			return this.title.trim() !== '' && this.technicalNameError === null
		},
	},
	watch: {
		showModal(open) {
			if (open) {
				this.title = this.page?.title ?? ''
				this.icon = this.page?.icon ?? null
				this.technicalName = this.page?.technicalName ?? ''
				this.description = this.page?.description ?? ''
			}
		},
	},
	methods: {
		/**
		 * Hands the fields to the parent. Fields left empty are sent empty, so they are removed;
		 * an empty technical name is left out, so the server keeps or gives one.
		 */
		submit() {
			if (!this.canSubmit) {
				return
			}
			const data = { title: this.title.trim(), icon: this.icon ?? '', description: this.description.trim() }
			const technicalName = this.technicalName.trim()
			if (technicalName !== '' && technicalName !== this.page?.technicalName) {
				data.technicalName = technicalName
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
