<template>
	<div v-if="activeTable" class="integration">
		<h3>{{ t('tables', 'API') }}</h3>
		<p>
			{{ t('tables', 'This is your API endpoint for this table') }}
		</p>
		<p class="url">
			{{ apiEndpointUrl }}
		</p>
		<h4>
			{{ t('tables', 'Your permissions') }}
		</h4>
		<ul>
			<li :class="{'notPermitted': !canReadDataActiveTable}">
				{{ canReadDataActiveTable ? '✓' : '' }} {{ t('tables', 'Read') }}
			</li>
			<li :class="{'notPermitted': !canCreateDataActiveTable}">
				{{ canCreateDataActiveTable ? '✓' : '' }} {{ t('tables', 'Create') }}
			</li>
			<li :class="{'notPermitted': !canUpdateDataActiveTable}">
				{{ canUpdateDataActiveTable ? '✓' : '' }} {{ t('tables', 'Update') }}
			</li>
			<li :class="{'notPermitted': !canDeleteDataActiveTable}">
				{{ canDeleteDataActiveTable ? '✓' : '' }} {{ t('tables', 'Delete') }}
			</li>
			<li :class="{'notPermitted': !canManageActiveTable}">
				{{ canManageActiveTable ? '✓' : '' }} {{ t('tables', 'Manage') }}
			</li>
		</ul>
	</div>
</template>

<script>
import { mapGetters, mapState } from 'vuex'
import { generateUrl } from '@nextcloud/router'
import tablePermissions from '../../main/mixins/tablePermissions.js'

export default {

	mixins: [tablePermissions],

	data() {
		return {
			loading: false,
		}
	},

	computed: {
		...mapState(['tables', 'tablesLoading']),
		...mapGetters(['activeTable']),
		apiEndpointUrl() {
			const params = {
				tableId: this.activeTable.id,
			}
			const url = '/apps/tables/api/1/tables/{tableId}'
			return window.location.protocol + '//' + window.location.host + generateUrl(url, params)
		},
	},
}
</script>
<style lang="scss" scoped>

.url {
	font-style: italic;
	overflow-x: auto
}

h4 {
	margin-top:calc(var(--default-grid-baseline) * 3);
}

ul {
	margin-left: calc(var(--default-grid-baseline) * 3);
}

.notPermitted {
	padding-left: 17px;
}

</style>
