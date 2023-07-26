<template>
	<div class="picker-content">
		<h2 class="picker-title">
			{{ getTitle }}
		</h2>
		<h3>
			{{ t('tables', '1. Choose view') }}
		</h3>
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
					<h3>
						{{ t('tables', '2. Choose preview type') }}
					</h3>
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
					<div v-if="referenceType === 'row'" class="row-picker">
						<h3>
							{{ t('tables', '3. Choose table row') }}
						</h3>
						<RowPickerTable
							:columns="viewObject.columns"
							:rows="viewObject.rows"
							:view="viewObject.view"
							:selected-row-id="selectedRowId"
							@update-selected-row-id="id => selectedRowId = id" />
					</div>
					<h3>
						{{ t('tables', 'Preview') }}
					</h3>
					<TableReferenceWidget v-if="referenceType === 'link'" :rich-object="viewObject.view" />
					<TableContentReferenceWidget v-else-if="referenceType === 'content'" :rich-object="enrichedView" />
					<RowReferenceWidget v-if="referenceType === 'row' && selectedRowId" :rich-object="enrichedView" />
				</div>
			</div>
		</div>
		<div class="select-button">
			<NcButton type="primary" :disabled="viewObject === null || (referenceType === 'row' && !selectedRowId)" :aria-label="t('tables', 'Select')" @click="selectReference">
				<template #icon>
					<Check :size="20" />
				</template>
				{{ t('tables', 'Confirm selection') }}
			</NcButton>
		</div>
	</div>
</template>

<script>

import Check from 'vue-material-design-icons/Check.vue'
import { NcEmptyContent, NcCheckboxRadioSwitch, NcButton } from '@nextcloud/vue'

import { translate as t } from '@nextcloud/l10n'

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

import LinkVariantIcon from 'vue-material-design-icons/LinkVariant.vue'
import { MetaColumns } from '../shared/components/ncTable/mixins/metaColumns.js'
import { parseCol } from '../shared/components/ncTable/mixins/columnParser.js'
import NcSearch from './NcSearch.vue'

import TableReferenceWidget from './TableReferenceWidget.vue'
import TableContentReferenceWidget from './TableContentReferenceWidget.vue'
import RowReferenceWidget from './RowReferenceWidget.vue'
import RowPickerTable from './RowPickerTable.vue'

export default {
	name: 'TableReferencePickerElement',
	components: {
		LinkVariantIcon,
		NcEmptyContent,
		NcCheckboxRadioSwitch,
		NcButton,
		NcSearch,
		TableReferenceWidget,
		TableContentReferenceWidget,
		RowReferenceWidget,
		RowPickerTable,
		Check

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
		enrichedView() {
			let view = this.viewObject.view
			view.columns = this.viewObject.columns
			if (this.referenceType === 'content') {
				view.rows = this.viewObject.rows
			} else if (this.referenceType === 'row') {
				view = {
					...view,
					rows: [this.viewObject.rows.find(row => row.id === this.selectedRowId)],
				}
			}
			return view
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
			let linkUrl = this.selectedResult.resourceUrl
			if (this.referenceType === 'content') {
				linkUrl += '/content'
			} else if (this.referenceType === 'row') {
				linkUrl += '/row/' + this.selectedRowId
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
	padding: 16px;
	overflow-y: scroll;
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
	padding: 16px 0;
}
.reference-option {
	padding: 4px 12px;

}
.select-button {
	bottom: 0;
	width: 100%;
	display: flex;
	align-items: end;
	flex-direction: column;
	position:sticky;
}

.row-picker {
	width: 100%;
}
.picker-title {
	display: flex;
    justify-content: center;
}
h3 {
	font-weight: bold;
	color: var(--color-text-lighter);
}

</style>
