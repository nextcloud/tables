<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="activeElement" class="sharing">
		<div>
			<ShareInternalLink
				v-if="sharePolicy.loaded && sharePolicy.canShare"
				:current-url="currentUrl"
				:is-view="isView" />
			<ShareForm
				v-if="sharePolicy.loaded && sharePolicy.canShare"
				:shares="shares"
				@add="addShare"
				@update="updateShare" />
			<ShareList :shares="shares" @remove="removeShare" @update="updateShare" />
			<SharingLinkList
				v-if="sharePolicy.loaded && sharePolicy.canShareLink"
				:shares="linkShares"
				@create-link-share="onCreateLinkShare"
				@delete-share="removeShare"
				@update-share="updateShare" />
		</div>
	</div>
</template>

<script>
import { useTablesStore } from '../../../store/store.js'
import { mapState, mapActions } from 'pinia'
import shareAPI from '../mixins/shareAPI.js'
import ShareForm from '../partials/ShareForm.vue'
import ShareList from '../partials/ShareList.vue'
import ShareInternalLink from '../partials/ShareInternalLink.vue'
import SharingLinkList from '../partials/SharingLinkList.vue'
import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'
import { isPublicLinkShare } from '../../../shared/utils/shareUtils.js'

export default {
	components: {
		ShareForm,
		ShareList,
		ShareInternalLink,
		SharingLinkList,
	},

	mixins: [shareAPI, permissionsMixin],
	data() {
		return {
			loading: false,
			sharePolicy: {
				loaded: false,
				canShare: false,
				canShareLink: false,
			},

			// shared with
			shares: [],
			linkShares: [],
		}
	},

	computed: {
		...mapState(useTablesStore, ['activeElement', 'isView']),
		currentUrl() {
			if (!this.activeElement) {
				return ''
			}
			const internalLink = window.location.protocol + '//' + window.location.host + generateUrl('/apps/tables/')

			if (this.isView) {
				return `${internalLink}#/view/${this.activeElement.id}`
			}
			return `${internalLink}#/table/${this.activeElement.id}`
		},
	},

	watch: {
		activeElement() {
			if (this.activeElement) {
				this.loadPolicyAndShares()
			}
		},
	},

	mounted() {
		if (this.activeElement) {
			this.loadPolicyAndShares()
		}
	},

	methods: {
		...mapActions(useTablesStore, ['setTableHasShares', 'setViewHasShares']),
		getCurrentUser,
		async loadPolicyAndShares() {
			if (!this.activeElement) {
				return
			}
			this.sharePolicy.loaded = false
			const policy = await this.getSharePolicyFromBE()
			this.sharePolicy = { loaded: true, ...policy }
			await this.loadSharesFromBE()
		},
		async loadSharesFromBE() {
			this.loading = true
			const allShares = await this.getSharedWithFromBE()

			this.shares = allShares.filter(share => !isPublicLinkShare(share))
			this.linkShares = allShares.filter(share => isPublicLinkShare(share))

			this.loading = false
		},
		async removeShare(share) {
			await this.removeShareFromBE(share.id)
			await this.loadSharesFromBE()
			// If no share is left, remove shared indication
			const hasStandardShares = this.shares.some(share =>
				(this.isView
					? (share.nodeType === 'view' && share.nodeId === this.activeElement.id)
					: (share.nodeType === 'table' && share.nodeId === this.activeElement.id)),
			)
			const hasLinkShares = this.linkShares.some(share =>
				(this.isView
					? (share.nodeType === 'view' && share.nodeId === this.activeElement.id)
					: (share.nodeType === 'table' && share.nodeId === this.activeElement.id)),
			)

			if (!hasStandardShares && !hasLinkShares) {
				if (this.isView) {
					await this.setViewHasShares({ viewId: this.activeElement.id, hasShares: false })
				} else {
					await this.setTableHasShares({ tableId: this.activeElement.id, hasShares: false })
				}
			}
		},
		async addShare(share) {
			if (!this.sharePolicy.canShare) {
				return
			}
			await this.sendNewShareToBE(share)
			await this.loadSharesFromBE()
		},
		async updateShare(data) {
			const shareId = data.id
			delete data.id
			await this.updateShareToBE(shareId, data)
			await this.loadSharesFromBE()
		},
		async onCreateLinkShare(password) {
			if (!this.sharePolicy.canShareLink) {
				return
			}
			const success = await this.createLinkShare(password)
			if (success) {
				await this.loadSharesFromBE()
			}
		},
	},
}
</script>
