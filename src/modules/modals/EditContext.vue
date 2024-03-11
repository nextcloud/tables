<template>
	<NcModal v-if="showModal" size="normal" @close="actionCancel">
		<div class="modal__content" data-cy="editContextModal">
			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Edit application') }}</h2>
				</div>
			</div>
			<div class="row space-T">
				<div class="col-4 mandatory">
					{{ t('tables', 'Title') }}
				</div>
				<div class="col-4" style="display: inline-flex;">
					<!-- TODO replace with Context's icon picker -->
					<NcEmojiPicker :close-on-select="true" @select="emoji => icon = emoji">
						<NcButton type="tertiary" :aria-label="t('tables', 'Select icon for application')"
							:title="t('tables', 'Select icon')" @click.prevent>
							{{ icon ? icon : '...' }}
						</NcButton>
					</NcEmojiPicker>
					<input v-model="title" :class="{ missing: errorTitle }" type="text"
						:placeholder="t('tables', 'Title of the application')">
				</div>
			</div>
			<div class="col-4 row space-T">
				<div class="col-4">
					{{ t('tables', 'Description') }}
				</div>
				<input v-model="description" type="text" :placeholder="t('tables', 'Description of the application')">
			</div>
			<div class="col-4 row space-T">
				<div class="col-4">
					{{ t('tables', 'Resources') }}
				</div>
				<NcContextResource :resources.sync="resources" />
			</div>

			<div class="row space-R">
				<div class="fix-col-4 end">
					<NcButton type="primary" @click="submit">
						{{ t('tables', 'Save') }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcEmojiPicker, NcButton } from '@nextcloud/vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'
import { mapGetters, mapState } from 'vuex'
import NcContextResource from '../../shared/components/ncContextResource/NcContextResource.vue'
import { NODE_TYPE_TABLE, NODE_TYPE_VIEW } from '../../shared/constants.js'

export default {
	name: 'EditContext',
	components: {
		NcModal,
		NcEmojiPicker,
		NcButton,
		NcContextResource,
	},
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		contextId: {
			type: Number,
			default: null,
		},
	},
	data() {
		return {
			title: '',
			icon: '',
			description: '',
			errorTitle: false,
			resources: [],
		}
	},
	computed: {
		...mapGetters(['getContext']),
		...mapState(['tables', 'views']),
		localContext() {
			return this.getContext(this.contextId)
		},
	},
	watch: {
		title() {
			if (this.title && this.title.length >= 200) {
				showError(t('tables', 'The title limit is reached with 200 characters.'))
				this.title = this.title.slice(0, 199)
			}
		},
		contextId() {
			if (this.contextId) {
				const context = this.getContext(this.contextId)
				this.title = context.name
				this.icon = context.iconName
				this.description = context.description
				this.resources = context ? [...this.getContextResources(context)] : []
			}
		},
	},
	methods: {
		actionCancel() {
			this.reset()
			this.$emit('close')
		},
		// TODO show edited changes if we're currently viewing the active context
		async submit() {
			if (this.title === '') {
				showError(t('tables', 'Cannot update context. Title is missing.'))
				this.errorTitle = true
			} else {
				const dataResources = this.resources.map(resource => {
					return {
						id: parseInt(resource.id),
						type: parseInt(resource.nodeType),
						// TODO get right permissions for the node
						permissions: 660,
					}
				})
				const data = {
					name: this.title,
					iconName: this.icon,
					description: this.description,
					nodes: dataResources,
				}
				const res = await this.$store.dispatch('updateContext', { id: this.contextId, data })
				if (res) {
					showSuccess(t('tables', 'Updated context "{icon}{contextTitle}".', { icon: this.icon ? this.icon + ' ' : '', contextTitle: this.title }))
					this.actionCancel()
				}
			}
		},
		reset() {
			const context = this.getContext(this.contextId)
			this.title = ''
			this.errorTitle = false
			this.icon = ''
			this.description = ''
			this.resources = context ? [...this.getContextResources(context)] : []
		},
		getContextResources(context) {
			const resources = []
			const nodes = Object.values(context.nodes)
			for (const node of nodes) {
				if (parseInt(node.node_type) === NODE_TYPE_TABLE || parseInt(node.node_type) === NODE_TYPE_VIEW) {
					const element = parseInt(node.node_type) === NODE_TYPE_TABLE ? this.tables.find(t => t.id === node.node_id) : this.views.find(v => v.id === node.node_id)
					if (element) {
						const elementKey = parseInt(node.node_type) === NODE_TYPE_TABLE ? 'table-' : 'view-'
						const resource = {
							title: element.title,
							emoji: element.emoji,
							key: `${elementKey}` + element.id,
							nodeType: parseInt(node.node_type) === NODE_TYPE_TABLE ? NODE_TYPE_TABLE : NODE_TYPE_VIEW,
							id: (element.id).toString(),
						}
						resources.push(resource)
					}
				}
			}
			return resources
		},
	},
}
</script>
