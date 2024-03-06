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

	methods: {
		removeResource(resource) {
			this.contextResource = this.contextResource.filter(r => r.key !== resource.key)
			this.localResource = this.contextResource
		},
		addResource(resource) {
			this.contextResource.push(resource)
			this.localResource = this.contextResource

		},

	},
}
</script>
