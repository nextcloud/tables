<template>
	<div>
		<div class="row space-B space-T">
			<div class="col-4 space-L">
				<h2 style="display: inline-flex; align-items: center;">
					{{ t('tables', 'Table') }}&nbsp;&nbsp;
					<NcActions :force-menu="true" type="secondary">
						<NcActionButton v-if="canManageElement(table)"
							:close-after-click="true"
							@click="$emit('create-column')">
							<template #icon>
								<TableColumnPlusAfter :size="20" decorative />
							</template>
							{{ t('tables', 'Create column') }}
						</NcActionButton>
						<NcActionButton v-if="canCreateRowInElement(table)"
							:close-after-click="true"
							@click="$emit('import', table)">
							<template #icon>
								<IconImport :size="20" decorative />
							</template>
							{{ t('tables', 'Import') }}
						</NcActionButton>
						<NcActionButton v-if="canShareElement(table)"
							:close-after-click="true"
							icon="icon-share"
							@click="$emit('toggle-share')">
							{{ t('tables', 'Share') }}
						</NcActionButton>
						<NcActionButton
							:close-after-click="true"
							@click="$emit('show-integration')">
							{{ t('tables', 'Integration') }}
							<template #icon>
								<Creation :size="20" />
							</template>
						</NcActionButton>
						<NcActionButton v-if="canManageElement(table)"
							type="error"
							:close-after-click="true"
							@click="deleteTable">
							<template #icon>
								<Delete :size="20" />
							</template>
							{{ t('tables', 'Delete table') }}
						</NcActionButton>
					</NcActions>
				</h2>
				<table class="table">
					<tbody>
						<tr>
							<td>{{ t('tables', 'Title') }}</td>
							<td>
								<div v-if="tableTitle === null" class="inline">
									<NcEmojiPicker :close-on-select="true" @select="emoji => updateTableEmoji(emoji)">
										<NcButton type="tertiary"
											:aria-label="t('tables', 'Select emoji for table')"
											:title="t('tables', 'Select emoji')"
											@click.prevent>
											{{ table.emoji ? table.emoji : '...' }}
										</NcButton>
									</NcEmojiPicker>
									{{ table.title }}&nbsp;&nbsp;
									<NcButton type="tertiary" :aria-label="t('tables', 'Edit table title')" @click="startEditingTableTitle">
										<template #icon>
											<IconRename :size="20" />
										</template>
									</NcButton>
								</div>
								<div v-else class="inline">
									<input ref="tableTitle" v-model="tableTitle" maxlength="200">&nbsp;&nbsp;
									<NcButton type="primary" :aria-label="t('tables', 'Save')" @click="updateTableTitle">
										<template #icon>
											<IconCheck :size="20" />
										</template>
									</NcButton>&nbsp;&nbsp;
									<NcButton icon="icon-close" type="tertiary" :aria-label="t('tables', 'Close')" @click="tableTitle = null">
										<template #icon>
											<IconClose :size="20" />
										</template>
									</NcButton>
								</div>
							</td>
						</tr>
						<tr>
							<td>{{ t('tables', 'Created at') }}</td>
							<td>{{ table.createdAt | niceDateTime }}</td>
						</tr>
						<tr>
							<td>{{ t('tables', 'Created by') }}</td>
							<td>{{ table.createdBy }}</td>
						</tr>
						<tr>
							<td>{{ t('tables', 'Ownership') }}</td>
							<td>{{ table.ownership }}</td>
						</tr>
						<tr>
							<td>{{ t('tables', 'Table ID') }}</td>
							<td>{{ table.id }}</td>
						</tr>
						<tr>
							<td>{{ t('tables', 'Shares') }}</td>
							<td>
								<NcLoadingIcon v-if="loadingTableShares" />
								<div v-else class="inline">
									<div v-for="share in tableShares" :key="share.id">
										<NcAvatar
											:display-name="share.receiverDisplayName"
											:user="share.receiver"
											:is-no-user="share.receiverType !== 'user'" />
									</div>
								</div>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

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
							<td style="display: inline-flex">
								{{ view.emoji + ' ' + view.title }}&nbsp;
								<NcButton :to="'/view/'+view.id" type="tertiary">
									<template #icon>
										<LinkIcon :size="20" />
									</template>
								</NcButton>
							</td>
							<td>{{ view.rowsCount }}</td>
							<td>{{ view.columns.length }}</td>
							<td>{{ view.lastEditAt | niceDateTime }}</td>
							<td v-if="view.hasShares">
								<NcLoadingIcon v-if="loadingViewShares" />
								<div v-else>
									<div v-for="share in viewShares[view.id]" :key="share.id">
										<NcAvatar
											:display-name="share.receiverDisplayName"
											:user="share.receiver"
											:is-no-user="share.receiverType !== 'user'" />
									</div>
								</div>
							</td>
							<td v-else />
							<td class="actions">
								<NcButton v-if="canManageElement(table)"
									type="secondary"
									:aria-label="t('tables', 'Edit view')"
									:close-after-click="true"
									@click="emit('tables:view:edit', { view })">
									<template #icon>
										<PlaylistEditIcon :size="20" />
									</template>
								</NcButton>
								<NcButton v-if="canManageElement(table)"
									type="error"
									:aria-label="t('tables', 'Delete view')"
									:close-after-click="true"
									@click="emit('tables:view:delete', view)">
									<template #icon>
										<Delete :size="20" />
									</template>
								</NcButton>
							</td>
						</tr>
						<tr class="footer">
							<td>{{ t('Tables', 'Total') }}</td>
							<td>{{ table.rowsCount }}</td>
							<td>{{ table.columnsCount }}</td>
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
import { mapState } from 'vuex'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'
import TableColumnPlusAfter from 'vue-material-design-icons/TableColumnPlusAfter.vue'
import PlaylistPlus from 'vue-material-design-icons/PlaylistPlus.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Moment from '@nextcloud/moment'
import IconImport from 'vue-material-design-icons/Import.vue'
import Creation from 'vue-material-design-icons/Creation.vue'
import IconRename from 'vue-material-design-icons/Rename.vue'
import IconClose from 'vue-material-design-icons/Close.vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import displayError from '../../../shared/utils/displayError.js'
import { NcActionButton, NcActions, NcAvatar, NcButton, NcLoadingIcon, NcEmojiPicker } from '@nextcloud/vue'
import PlaylistEditIcon from 'vue-material-design-icons/PlaylistEdit.vue'
import LinkIcon from 'vue-material-design-icons/Link.vue'
import { emit } from '@nextcloud/event-bus'
import { showError, showSuccess } from '@nextcloud/dialogs'

export default {
	components: {
		IconCheck,
		IconClose,
		IconRename,
		NcLoadingIcon,
		NcActionButton,
		Creation,
		IconImport,
		NcActions,
		NcButton,
		NcAvatar,
		TableColumnPlusAfter,
		PlaylistPlus,
		PlaylistEditIcon,
		LinkIcon,
		Delete,
		NcEmojiPicker,
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
		...mapState(['views']),
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
		emit,
		startEditingTableTitle() {
			this.tableTitle = this.table.title

			this.$nextTick(() => {
				this.$refs.tableTitle.focus()
			})
		},

		async updateTableEmoji(emoji) {
			const res = await this.$store.dispatch('updateTable', { id: this.table.id, data: { title: this.table.title, emoji } })
			if (res) {
				showSuccess(t('tables', 'Updated table "{emoji}{table}".', { emoji: emoji ? emoji + ' ' : '', table: this.table.title }))
			}
		},

		async updateTableTitle() {
			if (this.tableTitle === '' || this.tableTitle === null) {
				showError(t('tables', 'Cannot update table. Title is missing.'))
			} else {
				const res = await this.$store.dispatch('updateTable', { id: this.table.id, data: { title: this.tableTitle, emoji: this.table.emoji } })
				if (res) {
					showSuccess(t('tables', 'Updated table "{emoji}{table}".', { emoji: this.icon ? this.icon + ' ' : '', table: this.tableTitle }))
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
.table {
	border-collapse: collapse;
}

.table td .inline {
	display: inline-flex;
	align-items: center;
}

.table th,
.table td {
	padding-right: calc(var(--default-grid-baseline) * 4);
	padding-top: calc(var(--default-grid-baseline) * 1);
	padding-bottom: calc(var(--default-grid-baseline) * 1);
	text-align: left;
	background-color: var(--color-main-background-translucent);
	align-items: center;
}

.table th:last-child,
.table td:last-child {
	border-right: none;
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
	margin-left: calc(var(--default-grid-baseline) * 1);
}

td a {
	text-decoration: underline;
}

.footer td {
	font-weight: bold;
}

h2 {
	display: inline-flex;
	align-items: center;
}

</style>
