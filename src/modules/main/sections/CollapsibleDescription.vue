<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="description.trim() !== ''" class="collapsible-description" data-cy="collapsibleDescription">
		<NcButton variant="tertiary"
			:aria-expanded="open ? 'true' : 'false'"
			:aria-controls="opened ? panelId : undefined"
			data-cy="collapsibleDescriptionToggle"
			@click="toggle">
			<template #icon>
				<InformationOutline :size="20" />
			</template>
			{{ t('tables', 'Description of {title}', { title }, undefined, { escape: false }) }}
		</NcButton>
		<div v-if="opened"
			v-show="open"
			:id="panelId"
			class="collapsible-description__panel">
			<TableDescription :description="description" :read-only="true" />
		</div>
	</div>
</template>

<script>
import { useId } from 'vue'
import { NcButton } from '@nextcloud/vue'
import InformationOutline from 'vue-material-design-icons/InformationOutline.vue'
import TableDescription from './TableDescription.vue'

/**
 * The description of a table or view behind a button, for places where it would take the
 * room of the rows, such as a widget.
 */
export default {
	name: 'CollapsibleDescription',
	components: {
		InformationOutline,
		NcButton,
		TableDescription,
	},
	props: {
		description: {
			type: String,
			default: '',
		},
		// The title of the table or view, which names the button
		title: {
			type: String,
			default: '',
		},
	},
	setup() {
		return { panelId: 'collapsible-description-' + useId() }
	},
	data() {
		return {
			open: false,
			// The panel stays once it was opened, so its editor is built once
			opened: false,
		}
	},
	methods: {
		toggle() {
			this.open = !this.open
			this.opened = true
		},
	},
}
</script>

<style lang="scss" scoped>
.collapsible-description {
	padding-inline: calc(2 * var(--default-grid-baseline));

	&__panel {
		padding-block: var(--default-grid-baseline);
	}
}
</style>
