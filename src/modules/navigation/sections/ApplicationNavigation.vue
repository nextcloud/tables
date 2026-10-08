<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcAppNavigation :aria-label="context ? context.name : t('tables', 'Application')">
		<template #list>
			<div v-if="!context" class="icon-loading" />
			<template v-else>
				<div class="application-navigation__header" data-cy="application-nav-header">
					<ApplicationIcon :icon-name="context.iconName" :size="32" />
					<div class="application-navigation__title">
						<strong>{{ context.name }}</strong>
						<span v-if="context.description" class="application-navigation__description">{{ context.description }}</span>
					</div>
				</div>
				<NcAppNavigationItem v-for="item in menuItems"
					:key="item.id"
					:name="item.label"
					:to="item.targetType === 'url' ? undefined : menuItemRoute(item, context.id)"
					:href="item.targetType === 'url' ? item.url : undefined"
					:target="item.targetType === 'url' ? '_blank' : undefined"
					:class="{ active: isActive(item) }"
					data-cy="application-nav-item">
					<template #icon>
						<OpenInNew v-if="item.targetType === 'url'" :size="20" />
						<TableIcon v-else-if="item.targetType === 'table'" :size="20" />
						<ViewDashboardOutline v-else :size="20" />
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem v-if="hasResources"
					:name="t('tables', 'Overview')"
					:to="'/application/' + context.id"
					:class="{ active: !$route.params.itemName }"
					data-cy="application-nav-overview">
					<template #icon>
						<ViewGridOutline :size="20" />
					</template>
				</NcAppNavigationItem>
			</template>
		</template>
		<template #footer>
			<div v-if="context && ownsContext(context)" class="application-navigation__footer">
				<NcButton variant="tertiary" :href="configurationUrl" data-cy="application-nav-configure">
					<template #icon>
						<CogOutline :size="20" />
					</template>
					{{ t('tables', 'Configure in Tables') }}
				</NcButton>
			</div>
		</template>
	</NcAppNavigation>
</template>

<script>
import { NcAppNavigation, NcAppNavigationItem, NcButton } from '@nextcloud/vue'
import CogOutline from 'vue-material-design-icons/CogOutline.vue'
import OpenInNew from 'vue-material-design-icons/OpenInNew.vue'
import TableIcon from 'vue-material-design-icons/Table.vue'
import ViewDashboardOutline from 'vue-material-design-icons/ViewDashboardOutline.vue'
import ViewGridOutline from 'vue-material-design-icons/ViewGridOutline.vue'
import { generateUrl } from '@nextcloud/router'
import { mapState } from 'pinia'
import ApplicationIcon from '../../../shared/components/ApplicationIcon.vue'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'
import { menuItemRoute } from '../../../shared/utils/menuItems.js'
import { useTablesStore } from '../../../store/store.js'

/**
 * The navigation of an application opened on its own: its menu items are the entries.
 */
export default {
	name: 'ApplicationNavigation',
	components: {
		NcAppNavigation,
		NcAppNavigationItem,
		NcButton,
		CogOutline,
		OpenInNew,
		TableIcon,
		ViewDashboardOutline,
		ViewGridOutline,
		ApplicationIcon,
	},
	mixins: [permissionsMixin],
	computed: {
		...mapState(useTablesStore, ['activeContext']),
		context() {
			return this.activeContext
		},
		menuItems() {
			return [...(this.context?.menuItems ?? [])].sort((a, b) => a.order - b.order)
		},
		hasResources() {
			return Object.keys(this.context?.nodes ?? {}).length > 0
		},
		configurationUrl() {
			return generateUrl('/apps/tables/') + '#/application/' + this.context.id
		},
	},
	methods: {
		menuItemRoute,
		isActive(item) {
			return item.targetType !== 'url' && this.$route.params.itemName === item.technicalName
		},
	},
}
</script>

<style lang="scss" scoped>
.application-navigation {
	&__header {
		display: flex;
		align-items: center;
		gap: calc(3 * var(--default-grid-baseline, 4px));
		padding: calc(3 * var(--default-grid-baseline, 4px)) calc(4 * var(--default-grid-baseline, 4px));
	}

	&__title {
		display: flex;
		flex-direction: column;
		min-width: 0;
	}

	&__description {
		color: var(--color-text-maxcontrast);
		font-size: 0.9em;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__footer {
		padding: calc(2 * var(--default-grid-baseline, 4px));
	}
}
</style>
