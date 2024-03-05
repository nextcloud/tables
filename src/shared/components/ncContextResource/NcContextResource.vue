<template>
	<div>
		<div>
			<ResourceForm :resources="localResource" @add="addResource" />
			<ResourceList :resources="localResource" @remove="removeResource" />
		</div>
	</div>
</template>

<script>
import { mapGetters } from 'vuex'
import ResourceForm from './ResourceForm.vue'
import ResourceList from './ResourceList.vue'

export default {
	components: {
		ResourceForm,
		ResourceList,
	},

	props: {

		context: {
			type: Object,
			default: null,
		},
		// table or view
		resources: {
			type: Array,
			default: () => ([]),
		},
	},

	data() {
		return {
			loading: false,
			contextResource: this.resources,
		}
	},

	computed: {
		...mapGetters(['activeContext']),
		localResource: {
			get() {
				return this.contextResource
			},
			set(v) {
				this.$emit('update:resources', v)
			},
		},
	},

	watch: {
		activeContext() {
			if (this.activeContext) {
				this.loadResourcesFromBE()
			}
		},
	},

	mounted() {
		if (this.activeContext) {
			this.loadResourcesFromBE()
		}
	},

	methods: {
		// async loadResourcesFromBE() {
		// 	this.loading = true
		// 	this.resources = await this.getResourcesFromBE()
		// 	this.loading = false
		// },
		async removeResource(resource) {
			if (this.context) {
			} else {
				this.contextResource = this.contextResource.filter(r => r.key !== resource.key)
				this.localResource = this.contextResource
			}

			// await this.removeResourceFromBE(resource.id)
			// await this.loadResourcesFromBE()
			// // If no resource is left, remove resourced indication
			// if (this.isView) {
			// 	if (this.resources.find(resource => ((resource.nodeType === 'view' && resource.nodeId === this.activeElement.id) || (resource.nodeType === 'table' && resource.nodeId === this.activeElement.tableId))) === undefined) {
			// 		await this.$store.dispatch('setViewHasResources', { viewId: this.activeElement.id, hasResources: false })
			// 	}
			// } else {
			// 	if (this.resources.find(resource => (resource.nodeType === 'table' && resource.nodeId === this.activeElement.id)) === undefined) {
			// 		await this.$store.dispatch('setTableHasResources', { tableId: this.activeElement.id, hasResources: false })
			// 	}
			// }
		},
		async addResource(resource) {
			if (this.context) {
				await this.sendNewResourceToBE(resource)
				await this.loadResourcesFromBE()
			} else {
				this.contextResource.push(resource)
				this.localResource = this.contextResource
			}

		},
		// async updateResource(data) {
		// 	if (this.context) {
		// 		const resourceId = data.id
		// 		delete data.id
		// 		await this.updateResourceToBE(resourceId, data)
		// 		await this.loadResourcesFromBE()
		// 	}
		// 	else {
		// 		this.resources.push(resource)
		// 	}
		// },
		async getResourcesFromBE() {
			// try {
			// 	let res
			// 	let resources = []
			// 	if (this.isView) {
			// 		res = await axios.get(generateUrl('/apps/tables/resource/view/' + this.activeElement.id))
			// 		resources = resources.concat(res.data)
			// 		res = await axios.get(generateUrl('/apps/tables/resource/table/' + this.activeElement.tableId))
			// 		return resources.concat(res.data.filter(resource => resource.permissionManage))
			// 	} else {
			// 		res = await axios.get(generateUrl('/apps/tables/resource/table/' + this.activeElement.id))
			// 		return resources.concat(res.data)
			// 	}
			// } catch (e) {
			// 	displayError(e, t('tables', 'Could not fetch resources.'))
			// }
		},

		async sendNewResourceToBE(resource) {
			// const data = {
			// 	nodeType: this.isView ? 'view' : 'table',
			// 	nodeId: this.activeElement.id,
			// 	receiver: resource.user,
			// 	receiverType: (resource.isNoUser) ? 'group' : 'user',
			// 	permissionRead: true,
			// 	permissionCreate: true,
			// 	permissionUpdate: true,
			// 	permissionDelete: false,
			// 	permissionManage: false,
			// }
			// try {
			// 	await axios.post(generateUrl('/apps/tables/resource'), data)
			// } catch (e) {
			// 	displayError(e, t('tables', 'Could not create resource.'))
			// 	return false
			// }
			// if (this.isView) await this.$store.dispatch('setViewHasResources', { viewId: this.activeElement.id, hasResources: true })
			// else await this.$store.dispatch('setTableHasResources', { tableId: this.isView ? this.activeElement.tableId : this.activeElement.id, hasResources: true })
			// return true
		},
		async removeResourceFromBE(resourceId) {
			// try {
			// 	await axios.delete(generateUrl('/apps/tables/resource/' + resourceId))
			// } catch (e) {
			// 	displayError(e, t('tables', 'Could not remove resource.'))
			// }
		},
		async updateResourceToBE(resourceId, data) {
			// try {
			// 	await axios.put(generateUrl('/apps/tables/resource/' + resourceId + '/permission'), data)
			// } catch (e) {
			// 	displayError(e, t('tables', 'Could not update resource.'))
			// }
		},
	},
}
</script>
