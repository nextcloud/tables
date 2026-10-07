<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="applications-page" data-cy="applications-page">
		<div class="applications-page__header">
			<h1>{{ t('tables', 'Applications') }}</h1>
			<NcButton variant="primary" data-cy="applications-create" @click="createContext">
				<template #icon>
					<Plus :size="20" />
				</template>
				{{ t('tables', 'Create application') }}
			</NcButton>
		</div>

		<NcEmptyContent v-if="contexts.length === 0"
			:name="t('tables', 'No applications yet')"
			:description="t('tables', 'An application bundles views and tables behind one menu.')">
			<template #icon>
				<ViewGridOutline />
			</template>
		</NcEmptyContent>

		<ul v-else class="applications-page__list">
			<li v-for="context in sortedContexts" :key="context.id">
				<router-link :to="'/application/' + context.id" class="application-card" data-cy="application-card">
					<ApplicationIcon :icon-name="context.iconName" :size="32" />
					<div class="application-card__text">
						<h2 class="application-card__title">
							{{ context.name }}
						</h2>
						<p v-if="context.description" class="application-card__description">
							{{ context.description }}
						</p>
						<p class="application-card__meta">
							{{ n('tables', '%n menu item', '%n menu items', (context.menuItems ?? []).length) }}
							<template v-if="context.slug">
								· /{{ context.slug }}
							</template>
						</p>
					</div>
				</router-link>
			</li>
		</ul>

		<MainModals />
	</div>
</template>

<script>
import { NcButton, NcEmptyContent } from '@nextcloud/vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import ViewGridOutline from 'vue-material-design-icons/ViewGridOutline.vue'
import { emit } from '@nextcloud/event-bus'
import { mapState } from 'pinia'
import MainModals from '../modules/modals/Modals.vue'
import ApplicationIcon from '../shared/components/ApplicationIcon.vue'
import { useTablesStore } from '../store/store.js'

export default {
	name: 'Applications',
	components: {
		NcButton,
		NcEmptyContent,
		Plus,
		ViewGridOutline,
		MainModals,
		ApplicationIcon,
	},
	computed: {
		...mapState(useTablesStore, ['contexts']),
		sortedContexts() {
			return [...this.contexts].sort((a, b) => a.name.localeCompare(b.name))
		},
	},
	methods: {
		createContext() {
			emit('tables:context:create')
		},
	},
}
</script>

<style lang="scss" scoped>
.applications-page {
	width: 100%;
	padding: calc(4 * var(--default-grid-baseline, 4px));

	&__header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: calc(4 * var(--default-grid-baseline, 4px));
		margin-bottom: calc(4 * var(--default-grid-baseline, 4px));

		h1 {
			margin: 0;
			font-size: 24px;
			font-weight: bold;
		}
	}

	&__list {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
		gap: calc(4 * var(--default-grid-baseline, 4px));
		list-style: none;
	}
}

.application-card {
	display: flex;
	gap: calc(3 * var(--default-grid-baseline, 4px));
	padding: calc(4 * var(--default-grid-baseline, 4px));
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background-color: var(--color-main-background);
	color: inherit;
	text-decoration: none;

	&:hover,
	&:focus-visible {
		background-color: var(--color-background-hover);
	}

	&__text {
		min-width: 0;
	}

	&__title {
		margin: 0;
		font-size: var(--default-font-size);
		font-weight: bold;
	}

	&__description {
		margin: calc(1 * var(--default-grid-baseline, 4px)) 0 0;
	}

	&__meta {
		margin: calc(1 * var(--default-grid-baseline, 4px)) 0 0;
		color: var(--color-text-maxcontrast);
	}
}
</style>
