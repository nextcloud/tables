<template>
	<div v-if="activeView" class="integration">
		<h3>{{ t('tables', 'API') }}</h3>
		<p>
			{{ t('tables', 'This is your API endpoint for this view') }}
		</p>
		<p class="url">
			{{ apiEndpointUrl }}
		</p>
		<h4>
			{{ t('tables', 'Your permissions') }}
		</h4>
		<ul>
			<li :class="{'notPermitted': !canReadData(activeView)}">
				{{ canReadData(activeView) ? '✓' : '' }} {{ t('tables', 'Read') }}
			</li>
			<li :class="{'notPermitted': !canCreateRowInElement(activeView)}">
				{{ canCreateRowInElement(activeView) ? '✓' : '' }} {{ t('tables', 'Create') }}
			</li>
			<li :class="{'notPermitted': !canUpdateData(activeView)}">
				{{ canUpdateData(activeView) ? '✓' : '' }} {{ t('tables', 'Update') }}
			</li>
			<li :class="{'notPermitted': !canDeleteData(activeView)}">
				{{ canDeleteData(activeView) ? '✓' : '' }} {{ t('tables', 'Delete') }}
			</li>
			<li :class="{'notPermitted': !canManageElement(activeView)}">
				{{ canManageElement(activeView) ? '✓' : '' }} {{ t('tables', 'Manage') }}
			</li>
		</ul>
	</div>
</template>

<script>
import { mapGetters, mapState } from 'vuex'
import { generateUrl } from '@nextcloud/router'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'

export default {

	mixins: [permissionsMixin],

	data() {
		return {
			loading: false,
		}
	},

	computed: {
		...mapState(['tables', 'tablesLoading']),
		...mapGetters(['activeView']),
		apiEndpointUrl() {
			const params = {
				elementId: this.activeView.id,
			}
			const url = '/apps/tables/api/1/views/{elementId}'
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
