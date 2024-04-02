<template>
	<NcModal v-if="showModal" size="normal" @close="actionCancel">
		<div class="modal__content">
			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Create an application') }}</h2>
				</div>
			</div>
			<div class="row space-T">
				<div class="col-4 mandatory">
					{{ t('tables', 'Title') }}
				</div>
				<div class="col-4" style="display: inline-flex;">
					<NcIconPicker :close-on-select="true" @select="setIcon">
						<NcButton
							type="tertiary"
							:aria-label="t('tables', 'Select icon for the application')"
							:title="t('tables', 'Select icon')"
							@click.prevent>
							<template #icon>
								<NcIconSvgWrapper :svg="icon.svg" />
							</template>
						</NcButton>
					</NcIconPicker>
					<input ref="titleInput" v-model="title" :class="{ missing: errorTitle }" type="text"
						:placeholder="t('tables', 'Title of the new application')" @input="titleChangedManually">
				</div>
			</div>
			<div class="col-4 row space-T">
				<div class="col-4">
					{{ t('tables', 'Description') }}
				</div>
				<input v-model="description" type="text"
					:placeholder="t('tables', 'Description of the new application')">
			</div>
			<div class="col-4 row space-T">
				<div class="col-4">
					{{ t('tables', 'Resources') }}
				</div>
				<NcContextResource :resources.sync="resources" />
			</div>
			<div class="row space-R">
				<div class="fix-col-4 end space-T">
					<NcButton type="primary" :aria-label="t('tables', 'Create application')" @click="submit">
						{{ t('tables', 'Create application') }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcButton, NcIconSvgWrapper } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/dist/index.css'
import NcContextResource from '../../shared/components/ncContextResource/NcContextResource.vue'
import NcIconPicker from '../../shared/components/ncIconPicker/NcIconPicker.vue'
import svgHelper from '../../shared/components/ncIconPicker/mixins/svgHelper.js'

export default {
	name: 'CreateContext',
	components: {
		NcModal,
		NcIconPicker,
		NcButton,
		NcIconSvgWrapper,
		NcContextResource,
	},
	mixins: [svgHelper],
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			title: '',
			icon: {
				name: this.randomIcon(),
				svg: null,
			},
			customIconChosen: false,
			customTitleChosen: false,
			errorTitle: false,
			description: '',
			resources: [],
		}
	},
	watch: {
		title() {
			if (this.title.length >= 200) {
				showError(t('tables', 'The title limit is reached with 200 characters. Please use a shorter title.'))
				this.title = this.title.slice(0, 199)
			}
		},
		showModal() {
			// every time when the modal opens chose a new icon
			this.icon.name = this.setIcon(this.randomIcon())
			// this.$nextTick(() => this.$refs.titleInput?.focus())
		},
	},
	async mounted() {
		await this.setIcon(this.randomIcon())
	},
	methods: {
		titleChangedManually() {
			this.customTitleChosen = true
		},
		async setIcon(iconName) {
			this.icon.name = iconName
			this.icon.svg = await this.getContextIcon(iconName)

			this.customIconChosen = true
		},
		actionCancel() {
			this.reset()
			this.$emit('close')
		},
		async submit() {
			if (this.title === '') {
				showError(t('tables', 'Cannot create new context. Title is missing.'))
				this.errorTitle = true
			} else {
				const newContextId = await this.sendNewContextToBE()
				if (newContextId) {
					await this.$router.push('/application/' + newContextId)
					this.actionCancel()
				}
			}
		},
		randomIcon() {
			const iconNames = ['alarm', 'apps', 'bank', 'bell', 'book', 'briefcase', 'camera', 'cellphone', 'earth', 'equalizer', 'file', 'football', 'heart', 'home', 'laptop', 'lightbulb', 'lock', 'movie', 'newspaper', 'rocket', 'star']
			return iconNames[~~(Math.random() * iconNames.length)]
		},
		async sendNewContextToBE() {
			const dataResources = this.resources.map(resource => {
				return {
					id: parseInt(resource.id),
					type: parseInt(resource.nodeType),
					permissions: 660,
				}
			})
			const data = {
				name: this.title,
				iconName: this.icon.name,
				description: this.description,
				nodes: dataResources,
			}
			const res = await this.$store.dispatch('insertNewContext', { data })
			if (res) {
				return res.id
			} else {
				showError(t('tables', 'Could not create new table'))
			}
		},
		reset() {
			this.title = ''
			this.errorTitle = false
			this.icon.name = this.randomIcon()
			this.customIconChosen = false
			this.customTitleChosen = false
		},
	},
}
</script>

<style lang="scss" scoped>
.modal__content {
	padding-right: 0 !important;
}
</style>
