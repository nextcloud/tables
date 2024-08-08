<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<div v-if="loading" class="icon-loading" />
		<ul v-if="getResources && getResources.length > 0" class="resource-list">
			<div v-for="resource in getResources" :key="resource.key" class="row">
				<div class="fix-col-2">
					<div style="display:flex; align-items: center; padding: 10px;">
						{{ resource.emoji }} &nbsp; {{ resource.title }}
					</div>
				</div>
				<div class="fix-col-2" style="justify-content: end;">
					<NcActionButton :close-after-click="true" icon="icon-delete" @click="actionDelete(resource)">
						{{ t('tables', 'Delete') }}
					</NcActionButton>
				</div>
			</div>
		</ul>
		<div v-else>
			{{ t('tables', 'No selected resources') }}
		</div>
	</div>
</template>

<script>
import { NcActionButton } from '@nextcloud/vue'

export default {
	components: {
		NcActionButton,
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

	computed: {
		sortedResources() {
			return [...this.tableResources, ...this.viewResources].slice()
		},
		getResources() {
			return [...this.viewResources, ...this.tableResources]
		},
		viewResources() {
			return this.resources.filter(resource => resource.nodeType === 1)
		},
		tableResources() {
			return this.resources.filter(resource => resource.nodeType === 0)
		},
	},

	methods: {
		actionDelete(resource) {
			this.$emit('remove', resource)
		},
	},
}
</script>

<style lang="scss" scoped>
.resource-list li {
	display: flex;
	justify-content: space-between;
	line-height: 44px;
}

.resource-label {
	font-style: italic;
}
</style>
