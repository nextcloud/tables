<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="public-table-wrapper">
		<div v-if="loading" class="icon-loading" />

		<div v-else>
			<PublicElement
				:element="mockView"
				:columns="columns"
				:rows="rows"
				@download-csv="downloadCSV" />
		</div>
	</div>
</template>

<script>
import { mapActions, storeToRefs } from 'pinia'
import PublicElement from './PublicElement.vue'
import exportTableMixin from '../../../shared/components/ncTable/mixins/exportTableMixin.js'
import { useDataStore } from '../../../store/data.js'
import { computed } from 'vue'

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
			// TODO: remove mockView and replace with actual data
			mockView: {
				id: 'public',
				title: 'Public Share',
				description: '',
				isShared: true,
				onSharePermissions: {
					read: true,
					manage: false,
					share: false,
					update: false,
					create: false,
					delete: false,
				},
				permissionDelete: false,
				permissionManage: false,
				permissionShare: false,
				permissionUpdate: false,
				permissionCreate: false,
			},
		}
	},

	beforeMount() {
		this.loadData()
	},

	methods: {
		...mapActions(useDataStore, ['loadPublicColumnsFromBE', 'loadPublicRowsFromBE']),

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
