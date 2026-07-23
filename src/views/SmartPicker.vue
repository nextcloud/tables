<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="tables-smart-picker">
		<h2>
			{{ t('tables', 'Nextcloud Tables') }}
		</h2>
		<div class="selection-wrapper">
			<div class="selection">
				<div class="space-T space-B">
					<Search v-model:value="value" />
				</div>

				<div class="space-T space-B">
					<h3>{{ t('tables', 'Render mode') }}</h3>
					<div class="radio">
						<NcCheckboxRadioSwitch
							v-model="renderMode"
							value="link"
							name="render-mode"
							type="radio">
							<IconLink :size="20" />{{ t('tables', 'Link') }}
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch
							v-model="renderMode"
							value="content"
							name="render-mode"
							type="radio">
							<IconText :size="20" />{{ t('tables', 'Content') }}
						</NcCheckboxRadioSwitch>
					</div>
				</div>

				<div v-if="getLink" class="space-T space-B">
					<h3>{{ t('tables', 'Preview') }}</h3>
					<div class="preview space-T">
						<div v-if="previewLoading">
							<NcLoadingIcon />
						</div>
						<div v-else>
							<LinkReferenceWidget v-if="renderMode === 'link'" :rich-object="richObject" />
							<ContentReferenceWidget v-if="renderMode === 'content'" :rich-object="richObject" />
						</div>
					</div>
				</div>
				<NcEmptyContent v-else>
					<template #icon>
						<IconTables />
					</template>
				</NcEmptyContent>
			</div>
		</div>

		<div class="select-button">
			<NcButton type="primary" :disabled="value === null" :aria-label="t('tables', 'Select')" @click="selectReference">
				<template #icon>
					<IconCheck :size="20" />
				</template>
				{{ t('tables', 'Insert') }}
			</NcButton>
		</div>
	</div>
</template>
<script>
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import { NcButton, NcCheckboxRadioSwitch, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import IconTables from '../shared/assets/icons/IconTables.vue'
import Search from './sections/Search.vue'
import IconLink from 'vue-material-design-icons/Link.vue'
import IconText from 'vue-material-design-icons/Text.vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import displayError from '../shared/utils/displayError.js'
import { useTablesStore } from '../store/store.js'
import { useDataStore } from '../store/data.js'
import { createPinia, setActivePinia } from 'pinia'
import LinkReferenceWidget from './LinkReferenceWidget.vue'
import ContentReferenceWidget from './ContentReferenceWidget.vue'

const pinia = createPinia()
setActivePinia(pinia)

export default {

	components: {
		IconTables,
		IconLink,
		IconText,
		IconCheck,
		NcEmptyContent,
		Search,
		NcCheckboxRadioSwitch,
		NcButton,
		LinkReferenceWidget,
		ContentReferenceWidget,
		NcLoadingIcon,
	},

	data() {
		return {
			renderMode: 'link', // { link, content }
			value: null,
			previewLoading: false,
			richObject: {
				emoji: '',
				link: '',
				ownerDisplayName: '',
				ownership: '',
				rowsCount: 0,
				title: '',
				type: '',
				id: '',
				columns: [],
				rows: [],
			},
			tablesStore: null,
			dataStore: null,
		}
	},

	computed: {
		getLink() {
			if (!this.value) {
				return ''
			}

			const suffix = this.renderMode === 'content' ? '/content' : ''
			return window.location.protocol + '//' + window.location.host + generateUrl('/apps/tables/#/' + this.value?.type + '/' + this.value?.value + suffix)
		},
	},

	watch: {
		renderMode() {
			this.updateContent()
		},
		value() {
			this.updateRichObject()
			this.updateContent()
		},
	},

	async mounted() {
		this.tablesStore = useTablesStore()
		this.dataStore = useDataStore()
	},

	methods: {
		t,
		n,
		async updateContent() {
			if (this.renderMode === 'content') {
				this.previewLoading = true

				await this.loadColumnsForContentPreview()
				await this.loadRowsForContentPreview()

				this.previewLoading = false
			} else {
				delete this.richObject.rows
				delete this.richObject.columns
			}
		},
		selectReference() {
			// The reference-picker framework listens for a DOM 'submit' event on the
			// rendered element (element.addEventListener('submit', e => onSubmit(e.detail))).
			// Vue 3 has no $on bridge like Vue 2, so dispatch a native CustomEvent.
			this.$el?.dispatchEvent(new CustomEvent('submit', { detail: this.getLink, bubbles: true }))
		},
		updateRichObject() {
			if (!this.value) return

			this.richObject.emoji = this.value.emoji
			this.richObject.link = this.getLink
			this.richObject.ownerDisplayName = this.value.ownerDisplayName
			this.richObject.ownership = this.value.owner
			this.richObject.rowsCount = this.value.rowsCount
			this.richObject.title = this.value.label
			this.richObject.type = this.value.type
			this.richObject.id = this.value.value
		},
		async loadColumnsForContentPreview() {
			if (this.value === null) {
				return
			}

			try {
				const res = await axios.get(generateUrl('/apps/tables/api/1/' + this.value.type + 's/' + this.value.value + '/columns'))
				this.richObject.columns = res.data
			} catch (e) {
				displayError(e, t('tables', 'Could not fetch columns for content preview.'))
			}
		},
		async loadRowsForContentPreview() {
			if (this.value === null) {
				return
			}

			try {
				const res = await axios.get(generateUrl('/apps/tables/row/' + this.value.type + '/' + this.value.value))
				this.richObject.rows = res.data
			} catch (e) {
				displayError(e, t('tables', 'Could not fetch rows for content preview.'))
			}
		},
	},

}
</script>
