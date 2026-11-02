<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="description.trim() !== ''" class="collapsible-description" data-cy="collapsibleDescription">
		<NcButton variant="tertiary"
			:aria-expanded="open ? 'true' : 'false'"
			:aria-controls="panelId"
			data-cy="collapsibleDescriptionToggle"
			@click="open = !open">
			<template #icon>
				<InformationOutline :size="20" />
			</template>
			{{ open ? t('tables', 'Hide description') : t('tables', 'Show description') }}
		</NcButton>
		<div v-if="open" :id="panelId" class="collapsible-description__panel">
			<TableDescription :description="description" :read-only="true" />
		</div>
	</div>
</template>

<script>
import { NcButton } from '@nextcloud/vue'
import InformationOutline from 'vue-material-design-icons/InformationOutline.vue'
import TableDescription from './TableDescription.vue'

let instances = 0

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
	},
	data() {
		return {
			open: false,
			panelId: 'collapsible-description-' + (++instances),
		}
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
