<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="public-table-wrapper">
		<div v-if="loading" class="icon-loading" />

		<div v-else>
			<PublicElement :element="publicElement" :columns="columns" :rows="rows" @download-csv="downloadCSV" />
		</div>
	</div>
</template>

<script>
import { mapActions, storeToRefs } from 'pinia'
import PublicElement from './PublicElement.vue'
import exportTableMixin from '../../../shared/components/ncTable/mixins/exportTableMixin.js'
import { useDataStore } from '../../../store/data.js'
import { computed } from 'vue'
import { loadState } from '@nextcloud/initial-state'

const nodeData = loadState('tables', 'nodeData', null)
const sharePermissions = loadState('tables', 'sharePermissions', null)

export default {
	name: 'PublicMainWrapper',

	components: {
		PublicElement,
	},

	mixins: [exportTableMixin],

	props: {
		token: {
			type: String,
			required: true,
		},
	},

	setup(props) {
		const store = useDataStore()
		const { getColumns, getRows } = storeToRefs(store)

		const stateKey = 'public-' + props.token
		const rows = computed(() => getRows.value(false, stateKey))
		const columns = computed(() => getColumns.value(false, stateKey))

		return { rows, columns }
	},

	data() {
		return {
			loading: false,
			publicElement: {
				id: 'public',
				emoji: nodeData.emoji,
				title: nodeData.title,
				description: nodeData.description,
				isShared: false, // Setting as false to hide the user bubble
				onSharePermissions: {
					read: sharePermissions.read,
					create: sharePermissions.create,
					update: sharePermissions.update,
					delete: sharePermissions.delete,
					manage: false,
				},
			},
		}
	},

	beforeMount() {
		this.setPublicToken(this.token)
		this.loadData()
	},

	methods: {
		...mapActions(useDataStore, ['loadPublicColumnsFromBE', 'loadPublicRowsFromBE', 'setPublicToken']),

		async loadData() {
			this.loading = true
			try {
				await Promise.all([
					this.loadPublicColumnsFromBE({ token: this.token }),
					this.loadPublicRowsFromBE({ token: this.token }),
				])
			} catch (e) {
				console.error('Error loading public data', e)
			} finally {
				this.loading = false
			}
		},

		downloadCSV() {
			this.downloadCsv(this.rows, this.columns, 'public-export')
		},
	},
}
</script>

<style scoped lang="scss">
.public-table-wrapper {
	width: 100%;
	height: 100%;

	:deep(.tables-list__table) {
		margin-top: 60px;
	}
}
</style>
