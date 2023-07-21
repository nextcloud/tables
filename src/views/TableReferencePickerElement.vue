<template>
	<div class="picker-content">
		<h2>
			{{ getTitle }}
		</h2>
		<div class="smart-picker-search" :class="{ 'with-empty-content': true }">
			<NcSearch :selection.sync="selectedResult" :provider="provider" :search-placeholder="t('tables', 'Search table or view')" />
			<NcEmptyContent v-if="selectedResult === null"
				class="smart-picker-search--empty-content">
				<template #icon>
					<img v-if="provider.icon_url"
						class="provider-icon"
						:alt="providerIconAlt"
						:src="provider.icon_url">
					<LinkVariantIcon v-else />
				</template>
			</NcEmptyContent>
			<div v-else class="search-content">
				<div v-if="!viewObject" class="icon-loading" />
				<div v-else style="width:100%">
					<div class="reference-style">
						<NcCheckboxRadioSwitch :checked.sync="referenceType" value="link" type="radio" class="reference-option">
							{{ t('tables', 'Link to table') }}
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch :checked.sync="referenceType" value="content" type="radio" class="reference-option">
							{{ t('tables', 'Table with content') }}
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch :checked.sync="referenceType" value="row" type="radio" class="reference-option">
							{{ t('tables', 'Row from table') }}
						</NcCheckboxRadioSwitch>
					</div>
					<CustomTable
						v-if="referenceType != 'link'"
						:columns="viewObject.columns"
						:rows="viewObject.rows"
						:view="null"
						:view-setting="{}"
						:read-only="true" />
				</div>
			</div>
		</div>
		<div class="select-button">
			<NcButton type="primary" :disabled="viewObject === null" :aria-label="t('tables', 'Select')" @click="selectReference">
				{{ t('tables', 'Select') }}
			</NcButton>
		</div>
	</div>
</template>

<script>

import { NcEmptyContent, NcCheckboxRadioSwitch, NcButton } from '@nextcloud/vue'

import { translate as t } from '@nextcloud/l10n'

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

import LinkVariantIcon from 'vue-material-design-icons/LinkVariant.vue'
import CustomTable from '../shared/components/ncTable/sections/CustomTable.vue'
import { MetaColumns } from '../shared/components/ncTable/mixins/metaColumns.js'
import { parseCol } from '../shared/components/ncTable/mixins/columnParser.js'
import NcSearch from './NcSearch.vue'

export default {
	name: 'TableReferencePickerElement',
	components: {
		LinkVariantIcon,
		NcEmptyContent,
		CustomTable,
		NcCheckboxRadioSwitch,
		NcButton,
		NcSearch,
	},
	props: {
		providerId: {
			type: String,
			required: true,
		},
		accessible: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			selectedRowId: null,
			loading: false,
			searchQuery: '',
			selectedResult: null,
			viewObject: null,
			resultsBySearchProvider: {},
			searching: false,
			abortController: null,
			noOptionsText: t('Start typing to search'),
			providerIconAlt: t('Provider icon'),
			referenceType: 'link',
		}
	},
	computed: {
		provider() {
			//TODO:
			return {
				id: 'tables-ref-tables',
				title: 'Test',
				icon_url: 'http://nextcloud.local/apps-extra/tables/img/app-dark.svg',
				order: 10,
				search_providers_ids: ['tables-search-tables'],
			}
		},
		getTitle() {
			return t('tables', 'Table picker')
		},
	},
	watch: {
		selectedResult() {
			if (this.selectedResult) {
				this.loadView(this.selectedResult.resourceUrl)
			} else {
				this.viewObject = null
			}
		},
	},
	methods: {
		selectReference() {
			console.debug("Select", this.selectedResult, this.referenceType)
			let linkUrl = this.selectedResult.resourceUrl
			if (this.referenceType === 'content') {
				linkUrl += '/content'
			} else if (this.referenceType === 'row') {
				linkUrl += '/row/' + this.viewObject.rows[0].id
			}
			this.$emit('submit', linkUrl)
		},
		t,

		async loadView(url) {
			const viewId = url.split('/').slice(-1)[0]
			const viewObject = {}
			viewObject.view = (await axios.get(generateUrl('/apps/tables/view/' + viewId))).data
			const columns = (await axios.get(generateUrl('/apps/tables/column/view/' + viewId))).data
			const allColumns = columns.map(col => parseCol(col)).concat(MetaColumns.filter(col => viewObject.view.columns.includes(col.id)))
			viewObject.columns = allColumns.sort(function(a, b) {
				return viewObject.view.columns.indexOf(a.id) - viewObject.view.columns.indexOf(b.id)
			  })
			viewObject.rows = (await axios.get(generateUrl('/apps/tables/row/view/' + viewId))).data
			this.viewObject = viewObject
		},
	},
}
</script>
<style scoped lang="scss">
.picker-content {
	width: 100%;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: space-between;
	padding: 16px;
	overflow-y: auto;
	max-height: 800px;

	h2 {
		display: flex;
		align-items: center;
	}
	input {
		flex-grow: 1;
	}
	.input-loading {
		padding: 0 4px;
	}
}
.smart-picker-search {
	width: 100%;
	display: flex;
	flex-direction: column;
	min-height: 400px;

	&--empty-content {
		margin-top: auto !important;
		margin-bottom: auto !important;
	}

	.provider-icon {
		width: 150px;
		height: 150px;
		object-fit: contain;
		filter: var(--background-invert-if-dark);
	}
}

.reference-style {
	display: flex;
	justify-content: space-between;
}
.search-content {
	padding: 16px;
}
.reference-option {
	padding: 4px 12px;

}
.select-button {
	padding-top: 16px;
	width: 100%;
	display: flex;
	align-items: end;
	flex-direction: column;
}
</style>
