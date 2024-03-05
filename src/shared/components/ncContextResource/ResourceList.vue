<template>
	<div>
		<h3>{{ t('tables', 'Resources') }}</h3>
		<div v-if="loading" class="icon-loading" />
		<ul v-if="getResources && getResources.length > 0" class="resourceList">
			<div v-for="resource in getResources" :key="resource.key" class="row">
				<div class="fix-col-2">
					<div style="display:flex; align-items: center;">
						{{ resource.emoji }}
					</div>
					<div> {{ resource.title }} </div>
				</div>
				<div class="fix-col-2" style="justify-content: end;">
					<NcActionButton :close-after-click="true" icon="icon-delete" @click="actionDelete(resource)">
						{{ t('tables', 'Delete') }}
					</NcActionButton>
				</div>
			</div>
		</ul>
		<div v-else>
			{{ t('tables', 'No resources') }}
		</div>
	</div>
</template>

<script>
import { mapGetters, mapState } from 'vuex'
import { NcActions, NcActionButton, NcAvatar, NcActionCheckbox, NcActionCaption, NcActionSeparator, NcActionText } from '@nextcloud/vue'

export default {
	components: {
		NcAvatar,
		NcActionButton,
		NcActions,
		NcActionText,
		NcActionCheckbox,
		NcActionCaption,
		NcActionSeparator,
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
		...mapState(['tables', 'tablesLoading']),
		sortedResources() {
			return [...this.tableResources, ...this.viewResources].slice()
		},
		getResources() {
			return [...this.viewResources, ...this.tableResources]
		},
		viewResources() {
			return this.resources.filter(resource => resource.nodeType === 'view')
		},
		tableResources() {
			return this.resources.filter(resource => resource.nodeType === 'table')
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
.resourceList li {
    display: flex;
    justify-content: space-between;
    line-height: 44px;
}
</style>
