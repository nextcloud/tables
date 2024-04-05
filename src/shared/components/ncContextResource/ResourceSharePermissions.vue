<template>
	<div>
		<h3>{{ t('tables', 'Sharing') }}</h3>
		<ul v-if="resources && resources.length > 0" class="shares-list">
			<div v-for="resource in resources"
				:key="resource.key"
				class="row">
				<div class="fix-col-2">
					<div style="display:flex; align-items: center; padding: 10px;">
						{{ resource.emoji }} &nbsp; {{ resource.title }}
					</div>
				</div>
				<div class="fix-col-2" style="justify-content: end;">
					<NcActions :force-menu="true">
						<template>
							<NcActionCaption :name="t('tables', 'Permissions')" />
							<NcActionCheckbox
								:disabled="true"
								@check="updatePermission(resource, 'read', true)"
								@uncheck="updatePermission(resource, 'read', false)">
								{{ t('tables', 'Read data') }}
							</NcActionCheckbox>
							<!-- <NcActionCheckbox :checked.sync="share.permissionRead"
								:disabled="share.permissionManage || share.permissionUpdate || share.permissionDelete"
								@check="updatePermission(share, 'read', true)"
								@uncheck="updatePermission(share, 'read', false)">
								{{ t('tables', 'Read data') }}
							</NcActionCheckbox>
							<NcActionCheckbox :checked.sync="share.permissionCreate"
								:disabled="share.permissionManage"
								@check="updatePermission(share, 'create', true)"
								@uncheck="updatePermission(share, 'create', false)">
								{{ t('tables', 'Create data') }}
							</NcActionCheckbox>
							<NcActionCheckbox :checked.sync="share.permissionUpdate"
								:disabled="share.permissionManage"
								@check="updatePermission(share, 'update', true)"
								@uncheck="updatePermission(share, 'update', false)">
								{{ t('tables', 'Update data') }}
							</NcActionCheckbox>
							<NcActionCheckbox :checked.sync="share.permissionDelete"
								:disabled="share.permissionManage"
								@check="updatePermission(share, 'delete', true)"
								@uncheck="updatePermission(share, 'delete', false)">
								{{ t('tables', 'Delete data') }}
							</NcActionCheckbox> -->
						</template>
					</NcActions>
				</div>
			</div>
		</ul>
		<div v-else>
			{{ t('tables', 'No shares') }}
		</div>
	</div>
</template>

<script>
import { NcActions, NcActionCheckbox, NcActionCaption } from '@nextcloud/vue'

export default {
	components: {
		NcActions,
		NcActionCheckbox,
		NcActionCaption,
	},

	props: {
		resources: {
			type: Array,
			default: () => ([]),
		},
	},

	data() {
		return {
			loading: false,
		}
	},

	methods: {
		updatePermission(share, permission, value) {
			this.$emit('update', { id: share.id, permission, value })
		},
	},
}
</script>

<style lang="scss" scoped>

	.shares-list li {
		display: flex;
		justify-content: space-between;
		line-height: 44px;
	}

	.high-line-height {
		line-height: 35px;
	}

	.manage-button {
		display: flex;
		justify-content: center;
	}

</style>
