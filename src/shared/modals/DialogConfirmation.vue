<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="showModal" :out-transition="true"
		size="normal" close-on-click-outside :name="title" data-cy="confirmDialog"
		@closing="$emit('cancel')">
		<div class="row">
			<div v-if="description" class="col-4">
				<p>{{ description }}</p>
			</div>
		</div>
		<template #actions>
			<NcButton :aria-label="cancelTitle" :type="cancelClass" @click="$emit('cancel')">
				{{ cancelTitle }}
			</NcButton>
			<NcButton :type="confirmClass" :aria-label="confirmTitle" @click="$emit('confirm')">
				{{ confirmTitle }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcDialog, NcButton } from '@nextcloud/vue'

export default {
	name: 'DialogConfirmation',
	components: {
		NcDialog,
		NcButton,
	},
	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		title: {
			type: String,
			default: t('tables', 'Confirmation'),
		},
		description: {
			type: String,
			default: null,
		},
		cancelTitle: {
			type: String,
			default: t('tables', 'Cancel'),
		},
		cancelClass: {
			type: String,
			default: 'tertiary',
		},
		confirmTitle: {
			type: String,
			default: t('tables', 'Confirm'),
		},
		confirmClass: {
			type: String,
			default: 'success', // primary, secondary, tertiary, tertiary-no-background, tertiary-on-primary, error, warning, success
		},
	},
}
</script>
<style scoped>

	.modal__content {
		padding: 20px;
		min-height: inherit;
		max-height: inherit;
	}

</style>
