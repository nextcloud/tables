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
					<NcIconPicker :close-on-select="true" @select="setIcon">
						<NcButton type="tertiary" :aria-label="t('tables', 'Select an icon for application')"
							:title="t('tables', 'Select icon')" @click.prevent>
							<template #icon>
								<NcIconSvgWrapper :svg="icon.svg" />
							</template>
						</NcButton>
					</NcIconPicker>
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
				<NcContextResource :resources.sync="resources" :receivers.sync="receivers" />
			</div>

			<div class="row space-T">
				<div class="fix-col-4 space-T justify-between">
					<NcButton v-if="!prepareDeleteContext" type="error" @click="prepareDeleteContext = true">
						{{ t('tables', 'Delete') }}
					</NcButton>
					<NcButton v-if="prepareDeleteContext" :wide="true" type="error" @click="actionDeleteContext">
						{{ t('tables', 'I really want to delete this application!') }}
					</NcButton>
					<div class="right-additional-button">
						<NcButton v-if="ownsContext(localContext)" @click="actionTransfer">
							{{ t('tables', 'Transfer application') }}
						</NcButton>
						<NcButton type="primary" @click="submit">
							{{ t('tables', 'Save') }}
						</NcButton>
					</div>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcButton, NcIconSvgWrapper } from '@nextcloud/vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { getCurrentUser } from '@nextcloud/auth'
import '@nextcloud/dialogs/dist/index.css'
import { mapGetters, mapState } from 'vuex'
import NcContextResource from '../../shared/components/ncContextResource/NcContextResource.vue'
import NcIconPicker from '../../shared/components/ncIconPicker/NcIconPicker.vue'
import { NODE_TYPE_TABLE, NODE_TYPE_VIEW, PERMISSION_READ, PERMISSION_CREATE, PERMISSION_UPDATE, PERMISSION_DELETE } from '../../shared/constants.js'
import svgHelper from '../../shared/components/ncIconPicker/mixins/svgHelper.js'
import permissionBitmask from '../../shared/components/ncContextResource/mixins/permissionBitmask.js'
import { emit } from '@nextcloud/event-bus'
import permissionsMixin from '../../shared/components/ncTable/mixins/permissionsMixin.js'

export default {
	name: 'EditContext',
	components: {
		NcModal,
		NcButton,
		NcIconPicker,
		NcIconSvgWrapper,
		NcContextResource,
	},
	mixins: [svgHelper, permissionBitmask, permissionsMixin],
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
			icon: {
				name: 'equalizer',
				svg: null,
			},
			description: '',
			errorTitle: false,
			resources: [],
			receivers: [],
			PERMISSION_READ,
			PERMISSION_CREATE,
			PERMISSION_UPDATE,
			PERMISSION_DELETE,
			prepareDeleteContext: false,
		}
	},
	computed: {
		...mapGetters(['getContext']),
		...mapState(['tables', 'views', 'activeContextId']),
		localContext() {
			return this.getContext(this.contextId)
		},
	},
	watch: {
		title() {
			if (this.title && this.title.length >= 200) {
				showError(t('tables', 'The title character limit is 200 characters. Please use a shorter title.'))
				this.title = this.title.slice(0, 199)
			}
		},
		contextId() {
			if (this.contextId) {
				const context = this.getContext(this.contextId)
				this.title = context.name
				this.setIcon(this.localContext.iconName)
				this.description = context.description
				this.resources = context ? this.getContextResources(context) : []
				this.receivers = context ? this.getContextReceivers(context) : []
			}
		},
	},
	methods: {
		async setIcon(iconName) {
			this.icon.name = iconName
			this.icon.svg = await this.getContextIcon(iconName)
		},
		actionCancel() {
			this.reset()
			this.$emit('close')
		},
		async submit() {
			if (this.title === '') {
				showError(t('tables', 'Cannot update application. Title is missing.'))
				this.errorTitle = true
			} else {
				const dataResources = this.resources.map(resource => {
					return {
						id: parseInt(resource.id),
						type: parseInt(resource.nodeType),
						permissions: this.getPermissionBitmaskFromBools(true /* ensure read permission is always true */, resource.permissionCreate, resource.permissionUpdate, resource.permissionDelete),
					}
				})
				const data = {
					name: this.title,
					iconName: this.icon.name,
					description: this.description,
					nodes: dataResources,
				}
				const context = this.getContext(this.contextId)

				const res = await this.$store.dispatch('updateContext', { id: this.contextId, data, previousReceivers: Object.values(context.sharing), receivers: this.receivers })
				if (res) {
					showSuccess(t('tables', 'Updated application "{contextTitle}".', { contextTitle: this.title }))
					this.actionCancel()
				}
			}
		},
		reset() {
			const context = this.getContext(this.contextId)
			this.title = ''
			this.errorTitle = false
			this.icon.name = 'equalizer'
			this.description = ''
			this.resources = context ? this.getContextResources(context) : []
			this.receivers = context ? this.getContextReceivers(context) : []
			this.prepareDeleteContext = false
		},
		getContextReceivers(context) {
			let sharing = Object.values(context.sharing)
			sharing = sharing.filter((share) => getCurrentUser().uid !== share.receiver)
			const receivers = sharing.map((share) => {
				return {
					id: share.receiver,
					displayName: share.receiver_display_name,
					icon: share.receiver_type === 'user' ? 'icon-user' : 'icon-group',
					isUser: share.receiver_type === 'user',
					key: share.receiver_type + '-' + share.receiver,
				}
			})
			return receivers
		},
		getPermissionFromBitmask(bitmask, permission) {
			return !!(bitmask & permission)
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
							permissionRead: this.getPermissionFromBitmask(node.permissions, PERMISSION_READ),
							permissionCreate: this.getPermissionFromBitmask(node.permissions, PERMISSION_CREATE),
							permissionUpdate: this.getPermissionFromBitmask(node.permissions, PERMISSION_UPDATE),
							permissionDelete: this.getPermissionFromBitmask(node.permissions, PERMISSION_DELETE),
						}
						resources.push(resource)
					}
				}
			}
			return resources
		},
		async actionDeleteContext() {
			this.prepareDeleteContext = false
			const context = this.getContext(this.contextId)
			const res = await this.$store.dispatch('removeContext', { context, receivers: context.sharing })
			if (res) {
				showSuccess(t('tables', 'Application "{context}" removed.', { context: this.title }))
				// if the active context was deleted, go to startpage
				if (this.contextId === this.activeContextId) {
					await this.$router.push('/').catch(err => err)
				}
				this.actionCancel()
			}

		},
		actionTransfer() {
			emit('tables:context:edit', null)
			emit('tables:context:transfer', this.localContext)
		},
	},
}
</script>
<style lang="scss" scoped>
.right-additional-button {
	display: inline-flex;
}

.right-additional-button>button {
	margin-left: calc(var(--default-grid-baseline) * 3);
}

:deep(.element-description) {
	padding-inline: 0 !important;
	max-width: 100%;
}
</style>
