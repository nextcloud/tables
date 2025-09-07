<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="showModal"
		:name="t('tables', 'Create an application')"
		size="normal"
		data-cy="createContextModal"
		@closing="actionCancel">
		<div class="modal__content">
			<div class="row space-T">
				<div class="col-4 mandatory">
					{{ t('tables', 'Title') }}
				</div>
				<div class="col-4 content-emoji">
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
					<input ref="titleInput" v-model="title" :class="{ missing: errorTitle }" type="text" data-cy="createContextTitle"
						:placeholder="t('tables', 'Title of the new application')" @input="titleChangedManually">
				</div>
			</div>
			<div class="col-4 row space-T">
				<div class="col-4">
					{{ t('tables', 'Description') }}
				</div>
				<input v-model="description" type="text" data-cy="createContextDes"
					:placeholder="t('tables', 'Description of the new application')">
			</div>
			<div class="col-4 row space-T">
				<div class="col-4">
					{{ t('tables', 'Resources') }}
				</div>
				<NcContextResource :resources.sync="resources" :receivers.sync="receivers" />
			</div>
			<div class="row space-T">
				<NcActionCheckbox :checked="showInNavigationDefault" data-cy="createContextShowInNavSwitch" @change="changeDisplayMode">
					{{ t('tables', 'Show in app list') }}
				</NcActionCheckbox>
				<p class="nav-display-subtext">
					{{ t('tables', 'This can be overridden by a per-account preference') }}
				</p>
			</div>
			<div class="row space-R row space-T">
				<div class="fix-col-4 end">
					<NcButton type="primary" :aria-label="t('tables', 'Create application')" data-cy="createContextSubmitBtn" @click="submit">
						{{ t('tables', 'Create application') }}
					</NcButton>
				</div>
			</div>
		</div>
	</NcDialog>
</template>

<script>
import { NcDialog, NcButton, NcIconSvgWrapper, NcActionCheckbox } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import NcContextResource from '../../shared/components/ncContextResource/NcContextResource.vue'
import NcIconPicker from '../../shared/components/ncIconPicker/NcIconPicker.vue'
import svgHelper from '../../shared/components/ncIconPicker/mixins/svgHelper.js'
import permissionBitmask from '../../shared/components/ncContextResource/mixins/permissionBitmask.js'
import { getCurrentUser } from '@nextcloud/auth'
import { NAV_ENTRY_MODE } from '../../shared/constants.ts'
import { useTablesStore } from '../../store/store.js'
import { mapActions } from 'pinia'

export default {
	name: 'CreateContext',
	components: {
		NcDialog,
		NcIconPicker,
		NcButton,
		NcIconSvgWrapper,
		NcContextResource,
		NcActionCheckbox,
	},
	mixins: [svgHelper, permissionBitmask],
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
			customTitleChosen: false,
			errorTitle: false,
			description: '',
			resources: [],
			receivers: [],
			showInNavigationDefault: false,
		}
	},
	watch: {
		title() {
			if (this.title.length >= 200) {
				showError(t('tables', 'The title character limit is 200 characters. Please use a shorter title.'))
				this.title = this.title.slice(0, 199)
			}
		},
		showModal() {
			// every time when the modal opens chose a new icon
			this.setIcon(this.randomIcon())
			// this.$nextTick(() => this.$refs.titleInput?.focus())
		},
	},
	async mounted() {
		await this.setIcon(this.randomIcon())
	},
	methods: {
		...mapActions(useTablesStore, ['insertNewContext']),
		titleChangedManually() {
			this.customTitleChosen = true
		},
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
				showError(t('tables', 'Cannot create new application. Title is missing.'))
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
					permissions: this.getPermissionBitmaskFromBools(true /* ensure read permission is always true */, resource.permissionCreate, resource.permissionUpdate, resource.permissionDelete),
				}
			})
			const data = {
				name: this.title,
				iconName: this.icon.name,
				description: this.description,
				nodes: dataResources,
			}
			// adding share to oneself to have navigation display control
			this.receivers.push(
				{
					id: getCurrentUser().uid,
					displayName: getCurrentUser().uid,
					icon: 'icon-user',
					isUser: true,
					key: 'user-' + getCurrentUser().uid,
				})
			const displayMode = this.showInNavigation ? 'NAV_ENTRY_MODE_ALL' : 'NAV_ENTRY_MODE_HIDDEN'
			const res = await this.insertNewContext({ data, previousReceivers: [], receivers: this.receivers, displayMode: NAV_ENTRY_MODE[displayMode] })
			if (res) {
				return res.id
			} else {
				showError(t('tables', 'Could not create new application'))
			}
		},
		changeDisplayMode() {
			this.showInNavigation = !this.showInNavigation
		},
		reset() {
			this.title = ''
			this.errorTitle = false
			this.setIcon(this.randomIcon())
			this.customTitleChosen = false
			this.showInNavigationDefault = false
		},
	},
}
</script>

<style lang="scss" scoped>
.modal__content {
	padding-inline-end: 0 !important;

	.content-emoji {
		display: inline-flex;
		align-items: center;
	}

	.nav-display-subtext {
		color: var(--color-text-maxcontrast)
	}

	li {
		list-style: none;
	}
}
</style>
