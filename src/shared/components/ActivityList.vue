<!--
	- SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
	- SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="activity-list">
		<ActivityEntry v-for="activity in activities"
			:key="activity.activity_id"
			:activity="activity" />
		<div v-if="isLoading" class="icon-loading" />
		<div ref="sentinel" class="activity-list__sentinel" />
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import ActivityEntry from './ActivityEntry.vue'

const ACTIVITY_FETCH_LIMIT = 50

export default {
	name: 'ActivityList',
	components: {
		ActivityEntry,
	},
	props: {
		filter: {
			type: String,
			default: 'tables',
		},
		type: {
			type: String,
			required: true,
		},
		objectId: {
			type: Number,
			required: true,
		},
		objectType: {
			type: String,
			required: true,
		},
	},
	data() {
		return {
			activities: [],
			isLoading: false,
			since: 0,
			endReached: false,
			observer: null,
		}
	},
	watch: {
		objectId() {
			this.resetAndReload()
		},
	},
	mounted() {
		this.setupObserver()
	},
	beforeUnmount() {
		this.teardownObserver()
	},
	methods: {
		setupObserver() {
			this.observer = new IntersectionObserver((entries) => {
				if (entries[0]?.isIntersecting) {
					this.loadMore()
				}
			}, { threshold: 0.1 })
			if (this.$refs.sentinel) {
				this.observer.observe(this.$refs.sentinel)
			}
		},
		teardownObserver() {
			if (this.observer) {
				this.observer.disconnect()
				this.observer = null
			}
		},
		async loadMore() {
			if (this.isLoading || this.endReached) {
				return
			}
			this.isLoading = true
			try {
				await this.loadActivity()
			} finally {
				this.isLoading = false
			}
			await this.$nextTick()
			if (this.observer && this.$refs.sentinel && !this.endReached) {
				this.observer.unobserve(this.$refs.sentinel)
				this.observer.observe(this.$refs.sentinel)
			}
		},
		resetAndReload() {
			this.since = 0
			this.activities = []
			this.endReached = false
			this.loadMore()
		},
		async loadActivity() {
			const params = new URLSearchParams()
			params.append('format', 'json')
			params.append('since', this.since)
			params.append('limit', ACTIVITY_FETCH_LIMIT)

			const response = await axios.get(
				generateOcsUrl(`apps/activity/api/v2/activity/${this.filter}`) + '?' + params,
				{
					validateStatus: (status) => {
						return (status >= 200 && status < 300) || status === 304
					},
				},
			)

			if (response.status === 304) {
				this.endReached = true
				return []
			}

			let activities = response.data.ocs.data
			if (this.objectType === 'table') {
				activities = activities.filter((activity) => {
					return (activity.subject_rich[1]?.table?.id === this.objectId.toString() && !activity.subject_rich[1]?.view)
				})
			} else if (this.objectType === 'view') {
				activities = activities.filter((activity) => {
					return (activity.subject_rich[1]?.view?.id === this.objectId.toString())
				})
			} else if (this.objectType === 'row') {
				activities = activities.filter((activity) => {
					return (activity.subject_rich[1]?.row?.id === this.objectId.toString())
				})
			}
			this.activities.push(...activities)
			if (activities.length === 0) {
				this.endReached = true
				return []
			}
			this.since = (activities[activities.length - 1].activity_id)
			return activities
		},
	},
}
</script>

<style scoped>
.activity-list {
	margin-bottom: 100px;
}
</style>
