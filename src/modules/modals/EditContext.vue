<template>
	<NcModal v-if="showModal" size="normal" @close="actionCancel">
		<div class="modal__content" data-cy="editContextModal">
			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Edit context') }}</h2>
				</div>
			</div>
			<div class="row">
				<div class="row">
					{{ t('tables', 'Title') }}
				</div>
				<div class="col-4">
					<NcEmojiPicker :close-on-select="true" @select="emoji => icon = emoji">
						<NcButton type="tertiary" :aria-label="t('tables', 'Select emoji for table')"
							:title="t('tables', 'Select emoji')" @click.prevent>
							{{ icon ? icon : '...' }}
						</NcButton>
					</NcEmojiPicker>
					<input v-model="title" :class="{ missing: errorTitle }" type="text"
						:placeholder="t('tables', 'Title of the context')">
					<div class="row">
						<div class="col-4 mandatory">
							{{ t('tables', 'Description') }}
						</div>
						<input v-model="description" type="text"
							:placeholder="t('tables', 'Description of the context')">
					</div>
				</div>
				<div class="row">
					<div>
						{{ t('tables', 'Resources') }}
					</div>
					<NcContextResource :resources.sync="resources" />
				</div>
			</div>
			<div class="row">
				<div class="row">
					<div class="right-additional-button">
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
import { NcModal, NcEmojiPicker, NcButton, NcUserBubble } from '@nextcloud/vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'
import { mapGetters } from 'vuex'
import { emit } from '@nextcloud/event-bus'
import NcContextResource from '../../shared/components/ncContextResource/NcContextResource.vue'

export default {
	name: 'EditContext',
	components: {
		NcModal,
		NcEmojiPicker,
		NcButton,
		NcUserBubble,
		NcContextResource,
	},
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		context: {
			type: Object,
			default: null,
		},
	},
	data() {
		return {
			title: this.context?.name,
			icon: this.context?.iconName,
			description: this.context?.description,
			errorTitle: false,
			resources: [],
			contextId: this.context?.id,
		}
	},
	computed: {
		...mapGetters(['activeContext']),

	},
	watch: {
		title() {
			if (this.title && this.title.length >= 200) {
				showError(t('tables', 'The title limit is reached with 200 characters.'))
				this.title = this.title.slice(0, 199)
			}
		},
		context() {
			if (this.context) {
				this.title = this.context.name
				this.icon = this.context.iconName
				this.description = this.context.description
				// this.resources
				this.contextId = this.context.id
			}
		},
	},
	methods: {
		actionCancel() {
			this.reset()
			this.$emit('close')
		},
		async submit() {
			console.log('edit values', this.context, this.title)
			if (this.title === '') {
				showError(t('tables', 'Cannot update context. Title is missing.'))
				this.errorTitle = true
			} else {
				// TODO add nodes/resources during update
				const res = await this.$store.dispatch('updateContext', { id: this.context.id, data: { name: this.title, iconName: this.icon, description: this.description } })
				if (res) {
					showSuccess(t('tables', 'Updated context "{emoji}{contextTitle}".', { emoji: this.icon ? this.icon + ' ' : '', contextTitle: this.title }))
					this.actionCancel()
				}
			}
		},
		reset() {
			this.title = ''
			this.errorTitle = false
			this.icon = ''
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
</style>
