<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="sharing-link-list">
		<h3>{{ t('tables', 'Public links') }}</h3>

		<!-- List existing link shares -->
		<SharingEntryLink
			v-for="share in linkShares"
			:key="share.id"
			:share="share"
			@delete-share="onDeleteShare"
			@update-share="onUpdateShare" />

		<!-- Create new link share button -->
		<SharingEntryLink
			v-if="showCreateButton"
			:is-create-mode="true"
			@create-link-share="onCreateLinkShare" />
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import SharingEntryLink from './SharingEntryLink.vue'

export default {
	name: 'SharingLinkList',

	components: {
		SharingEntryLink,
	},

	props: {
		shares: {
			type: Array,
			default: () => [],
		},
	},

	computed: {
		linkShares() {
			return this.shares.filter(share => share.receiverType === 'link')
		},
		showCreateButton() {
			// Show create button if no links exist, or if you want to allow multiple.
			// Replicating "Create" if empty for now, but enabling multiple if we want "Add another".
			// Let's allow multiple.
			return true
		},
	},

	methods: {
		t,
		onCreateLinkShare(password) {
			this.$emit('create-link-share', password)
		},
		onDeleteShare(share) {
			this.$emit('delete-share', share)
		},
		onUpdateShare(data) {
			this.$emit('update-share', data)
		},
	},
}
</script>

<style scoped lang="scss">
.sharing-link-list {
	margin-bottom: 20px;

	h3 {
		font-weight: bold;
		margin-bottom: 10px;
		font-size: 16px;
	}
}
li {
    list-style-type: none;
}
</style>
