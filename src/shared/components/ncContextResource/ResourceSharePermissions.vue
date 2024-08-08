<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<div>
			{{ t('tables', 'Shared resources permissions') }}
		</div>
		<ul v-if="resources && resources.length > 0" class="shares-list">
			<div v-for="resource in resources" :key="resource.key" class="row">
				<div class="fix-col-2">
					<div style="display:flex; align-items: center; padding: 10px;">
						{{ resource.emoji }} &nbsp; {{ resource.title }}
					</div>
				</div>
				<div class="fix-col-2" style="justify-content: end;">
					<NcActions :force-menu="true" data-cy="resourceSharePermsActions">
						<NcActionCaption :name="t('tables', 'Permissions')" />
						<NcActionCheckbox :checked.sync="resource.permissionRead" :disabled="true"
							@check="updatePermission(resource, 'permissionRead', true)"
							@uncheck="updatePermission(resource, 'permissionRead', false)">
							{{ t('tables', 'Read resource') }}
						</NcActionCheckbox>
						<NcActionCheckbox :checked.sync="resource.permissionCreate"
							@check="updatePermission(resource, 'permissionCreate', true)"
							@uncheck="updatePermission(resource, 'permissionCreate', false)">
							{{ t('tables', 'Create resource') }}
						</NcActionCheckbox>
						<NcActionCheckbox :checked.sync="resource.permissionUpdate"
							@check="updatePermission(resource, 'permissionUpdate', true)"
							@uncheck="updatePermission(resource, 'permissionUpdate', false)">
							{{ t('tables', 'Update resource') }}
						</NcActionCheckbox>
						<NcActionCheckbox :checked.sync="resource.permissionDelete"
							@check="updatePermission(resource, 'permissionDelete', true)"
							@uncheck="updatePermission(resource, 'permissionDelete', false)">
							{{ t('tables', 'Delete resource') }}
						</NcActionCheckbox>
					</NcActions>
				</div>
			</div>
		</ul>
		<div v-else>
			{{ t('tables', 'No shared resources') }}
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
		updatePermission(resource, permission, value) {
			this.$emit('update', { resourceId: resource.id, permission, value })
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
</style>
