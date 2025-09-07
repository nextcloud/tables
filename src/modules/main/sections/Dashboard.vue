<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="table-dashboard">
		<div class="row space-T space-B">
			<div class="col-4 space-L">
				<h2>
					{{ t('tables', 'Views') }}&nbsp;&nbsp;
					<NcButton v-if="canManageElement(table)"
						type="secondary"
						:aria-label="t('tables', 'Create view')"
						:close-after-click="true" @click="$emit('create-view')">
						<template #icon>
							<PlaylistPlus :size="20" />
						</template>
					</NcButton>
				</h2>
			</div>
			<div class="col-4 space-L">
				<table class="table">
					<thead>
						<tr>
							<th>{{ t('tables', 'View') }} </th>
							<th>{{ t('tables', 'Rows') }} </th>
							<th>{{ t('tables', 'Columns') }} </th>
							<th>{{ t('tables', 'Last edited') }} </th>
							<th>{{ t('tables', 'Shares') }} </th>
							<th>{{ t('tables', 'Actions') }}</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="view in getViews" :key="view.id">
							<td style="display: inline-flex" class="link-to-view" @click="openView(view)">
								{{ view.emoji + ' ' + view.title }}&nbsp;
							</td>
							<td class="link-to-view number-column" @click="openView(view)">
								{{ view.rowsCount }}
							</td>
							<td class="link-to-view number-column" @click="openView(view)">
								{{ view.columnSettings ? Object.keys(view.columnSettings).length : 0 }}
							</td>
							<td class="link-to-view" @click="openView(view)">
								{{ view.lastEditAt | niceDateTime }}
							</td>
							<td v-if="view.hasShares">
								<NcLoadingIcon v-if="loadingViewShares" />
								<div v-else>
									<div v-for="share in viewShares[view.id]" :key="share.id" class="inline">
										<NcAvatar
											:display-name="share.receiverDisplayName"
											:user="share.receiver"
											:is-no-user="share.receiverType !== 'user'"
											:show-user-status="false" />
									</div>
								</div>
							</td>
							<td v-else />
							<td class="actions">
								<NcActions>
									<NcActionButton v-if="canManageElement(table)"
										type="secondary"
										:aria-label="t('tables', 'Edit view')"
										:close-after-click="true"
										@click="emit('tables:view:edit', { view })">
										<template #icon>
											<PlaylistEditIcon :size="20" />
										</template>
										{{ t('tables', 'Edit view') }}
									</NcActionButton>
									<NcActionButton v-if="canShareElement(table)"
										icon="icon-share"
										:close-after-click="true"
										@click="actionShowShare(view)">
										{{ t('tables', 'Share') }}
									</NcActionButton>
									<NcActionButton
										:close-after-click="true"
										@click="actionShowIntegration(view)">
										{{ t('tables', 'Integration') }}
										<template #icon>
											<Connection :size="20" />
										</template>
									</NcActionButton>
									<NcActionButton v-if="canManageElement(table)"
										type="error"
										:aria-label="t('tables', 'Delete view')"
										:close-after-click="true"
										@click="emit('tables:view:delete', view)">
										<template #icon>
											<DeleteOutline :size="20" />
										</template>
										{{ t('tables', 'Delete view') }}
									</NcActionButton>
								</NcActions>
							</td>
						</tr>
						<tr class="footer">
							<td>{{ t('Tables', 'Total') }}</td>
							<td class="number-column">
								{{ table.rowsCount }}
							</td>
							<td class="number-column">
								{{ table.columnsCount }}
							</td>
							<td />
							<td />
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</template>

<script>
import { mapState, mapActions } from 'pinia'
import { useTablesStore } from '../../../store/store.js'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'
import PlaylistPlus from 'vue-material-design-icons/PlaylistPlus.vue'
import DeleteOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import Moment from '@nextcloud/moment'
import Connection from 'vue-material-design-icons/Connection.vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import displayError from '../../../shared/utils/displayError.js'
import { NcActionButton, NcActions, NcAvatar, NcButton, NcLoadingIcon } from '@nextcloud/vue'
import PlaylistEditIcon from 'vue-material-design-icons/PlaylistEdit.vue'
import { emit } from '@nextcloud/event-bus'
import { showError, showSuccess } from '@nextcloud/dialogs'

export default {
	components: {
		NcLoadingIcon,
		NcActionButton,
		Connection,
		NcActions,
		NcButton,
		NcAvatar,
		PlaylistPlus,
		PlaylistEditIcon,
		DeleteOutline,
	},

	filters: {
		niceDateTime(value) {
			return Moment(value, 'YYYY-MM-DD HH:mm:ss').format('lll')
		},
	},

	mixins: [permissionsMixin],

	props: {
		table: {
			type: Object,
			default: null,
		},
	},

	data() {
		return {
			loadingTableShares: true,
			loadingViewShares: true,
			viewShares: {},
			tableShares: [],
			tableTitle: null,
			tableEmoji: null,
		}
	},
	computed: {
		...mapState(useTablesStore, ['views']),
		getViews() {
			return this.views.filter(v => v.tableId === this.table.id)
		},
	},
	watch: {
		table() {
			this.loadShares()
		},
	},

	mounted() {
		this.loadShares()
	},

	methods: {
		...mapActions(useTablesStore, ['updateTable']),
		emit,
		openView(view) {
			this.$router.push('/view/' + parseInt(view.id)).catch(err => err)
		},
		startEditingTableTitle() {
			this.tableTitle = this.table.title

			this.$nextTick(() => {
				this.$refs.tableTitle.focus()
			})
		},

		actionShowShare(view) {
			emit('tables:sidebar:sharing', { open: true, tab: 'sharing' })
			this.$router.push('/view/' + parseInt(view.id)).catch(err => err)
		},

		async actionShowIntegration(view) {
			emit('tables:sidebar:integration', { open: true, tab: 'integration' })
			await this.$router.push('/view/' + parseInt(view.id)).catch(err => err)
		},

		async updateTableEmoji(emoji) {
			const res = await this.updateTable({
				id: this.table.id,
				data: { title: this.table.title, emoji },
			})
			if (res) {
				showSuccess(t('tables', 'Updated table "{emoji}{table}".', {
					emoji: emoji ? emoji + ' ' : '',
					table: this.table.title,
				}))
			}
		},

		async updateTableTitle() {
			if (this.tableTitle === '' || this.tableTitle === null) {
				showError(t('tables', 'Cannot update table. Title is missing.'))
			} else {
				const res = await this.updateTable({
					id: this.table.id,
					data: { title: this.tableTitle, emoji: this.table.emoji },
				})
				if (res) {
					showSuccess(t('tables', 'Updated table "{emoji}{table}".', {
						emoji: this.icon ? this.icon + ' ' : '',
						table: this.tableTitle,
					}))
					this.tableTitle = null
				}
			}
		},

		deleteTable() {
			emit('tables:table:delete', this.table)
		},
		async getSharesForViewFromBE(viewId) {
			try {
				const res = await axios.get(generateUrl('/apps/tables/share/view/' + viewId))
				return res.data
			} catch (e) {
				displayError(e, t('tables', 'Could not fetch shares.'))
			}
		},

		async getSharesForTableFromBE(tableId) {
			try {
				const res = await axios.get(generateUrl('/apps/tables/share/table/' + tableId))
				return res.data
			} catch (e) {
				displayError(e, t('tables', 'Could not fetch shares.'))
			}
		},

		async loadShares() {
			// load shares for table
			this.loadingTableShares = true
			this.tableShares = await this.getSharesForTableFromBE(this.table.id)
			this.loadingTableShares = false

			// load shares for all views
			this.loadingViewShares = true
			for (const index in this.table.views) {
				const view = this.table.views[index]
				if (view.hasShares) {
					this.viewShares[view.id] = await this.getSharesForViewFromBE(view.id)
				}
			}
			this.tableShares = await this.getSharesForTableFromBE(this.table.id)
			this.loadingViewShares = false
		},
	},

}
</script>

<style lang="scss" scoped>

.table-dashboard {
	display: sticky;
	inset-inline-start: 0;
}

.table {
	border-collapse: collapse;
	width: 670px;
}

.table td .inline {
	display: inline-flex;
	align-items: center;
}

.table th,
.table td {
	padding-inline-end: calc(var(--default-grid-baseline) * 4);
	padding-top: calc(var(--default-grid-baseline) * 1);
	padding-bottom: calc(var(--default-grid-baseline) * 1);
	text-align: start;
	align-items: center;
}

.table th:last-child,
.table td:last-child {
	border-inline-end: none;
}

.table td:first-child {
	min-width: 150px;
}

.table th {
	color: var(--color-text-maxcontrast);
	box-shadow: inset 0 -1px 0 var(--color-border);
}

.dashboard-content {
	padding: calc(var(--default-grid-baseline) * 4);
}

.actions {
	display: flex;
	flex-direction: row;
}

td.actions {
	display: inline-flex;
}

td.actions button {
	margin-inline-start: calc(var(--default-grid-baseline) * 1);
}

tr:hover{
	cursor: pointer !important;
}

table{
	td.link-to-view{
		cursor: pointer !important;
	}
	td.number-column{
		text-align: end;
	}
}

.footer td {
	font-weight: bold;
}

h2 {
	display: inline-flex;
	align-items: center;
}

</style>
