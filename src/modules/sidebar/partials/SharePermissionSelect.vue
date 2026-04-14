<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="share-permission-select">
		<NcActions
			ref="quickShareActions"
			:menu-name="selectedOption"
			:aria-label="t('tables', 'Quick share options, current: {option}', { option: selectedOption })"
			variant="tertiary-no-background"
			force-name>
			<template #icon>
				<TriangleSmallDown :size="15" />
			</template>
			<NcActionButton
				v-for="option in options"
				:key="option.label"
				type="radio"
				:model-value="option.label === selectedOption"
				close-after-click
				@click="selectOption(option.label)">
				<template #icon>
					<component :is="option.icon" :size="20" />
				</template>
				{{ option.label }}
			</NcActionButton>
		</NcActions>

		<div v-if="showCustom" class="share-permission-select__custom">
			<NcCheckboxRadioSwitch
				:checked="share.permissionRead"
				data-cy="sharePermissionRead"
				@update:checked="updateCustom('permissionRead', $event)">
				{{ t('tables', 'Read') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch
				:checked="share.permissionCreate"
				data-cy="sharePermissionCreate"
				@update:checked="updateCustom('permissionCreate', $event)">
				{{ t('tables', 'Create') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch
				:checked="share.permissionUpdate"
				:disabled="!share.permissionRead"
				data-cy="sharePermissionUpdate"
				@update:checked="updateCustom('permissionUpdate', $event)">
				{{ t('tables', 'Update') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch
				:checked="share.permissionDelete"
				:disabled="!share.permissionRead"
				data-cy="sharePermissionDelete"
				@update:checked="updateCustom('permissionDelete', $event)">
				{{ t('tables', 'Delete') }}
			</NcCheckboxRadioSwitch>
		</div>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import EyeOutline from 'vue-material-design-icons/EyeOutline.vue'
import PencilOutline from 'vue-material-design-icons/PencilOutline.vue'
import Tune from 'vue-material-design-icons/Tune.vue'
import TriangleSmallDown from 'vue-material-design-icons/TriangleSmallDown.vue'

export default {
	name: 'SharePermissionSelect',

	components: {
		NcActions,
		NcActionButton,
		NcCheckboxRadioSwitch,
		TriangleSmallDown,
	},

	props: {
		share: {
			type: Object,
			required: true,
		},
	},

	emits: ['update:share'],

	data() {
		return {
			selectedOption: '',
			showCustom: false,
		}
	},

	computed: {
		viewOnlyText() {
			return t('tables', 'View only')
		},
		canEditText() {
			return t('tables', 'Can edit')
		},
		customText() {
			return t('tables', 'Custom permissions')
		},

		options() {
			return [
				{ label: this.viewOnlyText, icon: EyeOutline },
				{ label: this.canEditText, icon: PencilOutline },
				{ label: this.customText, icon: Tune },
			]
		},

		preSelectedOption() {
			const { permissionRead, permissionCreate, permissionUpdate, permissionDelete } = this.share

			const isViewOnly = permissionRead && !permissionCreate && !permissionUpdate && !permissionDelete
			const isCanEdit = permissionRead && permissionCreate && permissionUpdate && permissionDelete

			if (isViewOnly) {
				return this.viewOnlyText
			}
			if (isCanEdit) {
				return this.canEditText
			}
			return this.customText
		},
	},

	created() {
		this.selectedOption = this.preSelectedOption
		this.showCustom = this.selectedOption === this.customText
	},

	methods: {
		t,

		selectOption(label) {
			this.selectedOption = label
			this.showCustom = label === this.customText

			if (label !== this.customText) {
				this.$emit('update:share', this.permissionsForOption(label))
			}
		},

		permissionsForOption(label) {
			if (label === this.canEditText) {
				return {
					permissionRead: true,
					permissionCreate: true,
					permissionUpdate: true,
					permissionDelete: true,
				}
			}
			return {
				permissionRead: true,
				permissionCreate: false,
				permissionUpdate: false,
				permissionDelete: false,
			}
		},

		updateCustom(key, value) {
			const updated = {
				permissionRead: this.share.permissionRead,
				permissionCreate: this.share.permissionCreate,
				permissionUpdate: this.share.permissionUpdate,
				permissionDelete: this.share.permissionDelete,
				...{ [key]: value },
			}
			if (!updated.permissionRead) {
				updated.permissionUpdate = false
				updated.permissionDelete = false
			}
			this.$emit('update:share', updated)
		},
	},
}
</script>

<style scoped lang="scss">
.share-permission-select {
  display: flex;
  flex-direction: column;

  :deep(.action-item__menutoggle) {
    color: var(--color-primary-element) !important;
    font-size: 12.5px !important;
    height: auto !important;
    min-height: auto !important;

    .button-vue__text {
      font-weight: normal !important;
    }

    .button-vue__icon {
      height: 24px !important;
      min-height: 24px !important;
      width: 24px !important;
      min-width: 24px !important;
    }

    .button-vue__wrapper {
      flex-direction: row-reverse !important;
    }
  }

  &__custom {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    gap: 4px;
    padding-top: 8px;
  }
}
</style>
