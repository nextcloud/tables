<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="icon-field" data-cy="iconField">
		<NcIconPicker :close-on-select="true" @select="name => $emit('update:modelValue', name)">
			<NcButton ref="pickButton"
				variant="secondary"
				:aria-label="modelValue ? t('tables', '{label}: {icon}', { label, icon: iconLabel(modelValue) }) : t('tables', '{label}: none', { label })"
				data-cy="iconFieldPick">
				<template #icon>
					<NamedIcon :name="modelValue">
						<ImageOffOutline :size="20" />
					</NamedIcon>
				</template>
				{{ label }}
			</NcButton>
		</NcIconPicker>
		<NcButton v-if="modelValue && clearable"
			variant="tertiary"
			:aria-label="t('tables', 'Remove icon')"
			data-cy="iconFieldClear"
			@click="clear">
			<template #icon>
				<Close :size="20" />
			</template>
		</NcButton>
	</div>
</template>

<script>
import { NcButton } from '@nextcloud/vue'
import Close from 'vue-material-design-icons/Close.vue'
import ImageOffOutline from 'vue-material-design-icons/ImageOffOutline.vue'
import NcIconPicker from './NcIconPicker.vue'
import NamedIcon from './NamedIcon.vue'
import { iconLabel } from './iconNames.js'

/**
 * Picks a named icon for a form, with a button to remove it again.
 */
export default {
	name: 'IconField',
	components: {
		Close,
		ImageOffOutline,
		NamedIcon,
		NcButton,
		NcIconPicker,
	},
	props: {
		modelValue: {
			type: String,
			default: null,
		},
		label: {
			type: String,
			required: true,
		},
		clearable: {
			type: Boolean,
			default: true,
		},
	},
	emits: ['update:modelValue'],
	methods: {
		iconLabel,
		/**
		 * Removes the icon. The remove button goes with it, so focus moves to the button that
		 * picks an icon.
		 */
		clear() {
			this.$emit('update:modelValue', null)
			this.$nextTick(() => this.$refs.pickButton?.$el?.focus())
		},
	},
}
</script>

<style lang="scss" scoped>
.icon-field {
	display: flex;
	align-items: center;
	gap: var(--default-grid-baseline);
}
</style>
