<template>
	<div>
		<div>
			<ResourceForm :resources="localResources" @add="addResource" />
			<ResourceList :resources="localResources" @remove="removeResource" />
			<ResourceSharees :select-users="true" :select-groups="false" :receivers="localReceivers" @update="updateReceivers" />
			<ResourceSharePermissions :resources="localResources" @update="updateResourcePermissions" />
		</div>
	</div>
</template>

<script>
import { mapGetters } from 'vuex'
import ResourceForm from './ResourceForm.vue'
import ResourceList from './ResourceList.vue'
import ResourceSharePermissions from './ResourceSharePermissions.vue'
import ResourceSharees from './ResourceSharees.vue'

export default {
	components: {
		ResourceForm,
		ResourceList,
		ResourceSharePermissions,
		ResourceSharees,
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
		receivers: {
			type: Array,
			default: () => ([]),
		},
	},

	data() {
		return {
			loading: false,
			contextResources: this.resources,
			contextReceivers: this.receivers,
		}
	},

	computed: {
		...mapGetters(['activeContext']),
		localResources: {
			get() {
				return this.contextResources
			},
			set(v) {
				this.$emit('update:resources', v)
			},
		},
		localReceivers: {
			get() {
				return this.contextReceivers
			},
			set(v) {
				this.$emit('update:receivers', v)
			},
		},
	},

	methods: {
		removeResource(resource) {
			this.contextResources = this.contextResources.filter(r => r.key !== resource.key)
			this.localResources = this.contextResources
		},
		addResource(resource) {
			this.contextResources.push(resource)
			this.localResources = this.contextResources
		},
		updateReceivers(receivers) {
			this.localReceivers = receivers
		},
		updateResourcePermissions({ resourceId, permission, value }) {
			const resourceIndex = this.localResources.findIndex((resource) => resource.id === resourceId)
			if (resourceIndex !== -1) {
				this.localResources[resourceIndex][permission] = value
			}
		},

	},
}
</script>
