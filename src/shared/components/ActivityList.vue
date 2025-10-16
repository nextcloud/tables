<!--
	- SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
	- SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="activity-list">
		<div v-if="isLoading" class="icon icon-loading" />
		<ActivityEntry v-for="activity in activities"
			:key="activity.activity_id"
			:activity="activity" />
		<InfiniteLoading :identifier="objectId" @infinite="infiniteHandler" @change="changeObject">
			<div slot="spinner" class="icon-loading" />
			<div slot="no-more" />
			<div slot="no-results" />
		</InfiniteLoading>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import ActivityEntry from './ActivityEntry.vue'
import InfiniteLoading from 'vue-infinite-loading'

const ACTIVITY_FETCH_LIMIT = 50

export default {
	name: 'ActivityList',
	components: {
		ActivityEntry,
		InfiniteLoading,
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
		}
	},
	methods: {
		async loadActivity() {
			const params = new URLSearchParams()
			params.append('format', 'json')
			params.append('type', this.type)
			params.append('since', this.since)
			params.append('object_type', this.objectType)
			params.append('object_id', '' + this.objectId)
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
			if (this.objectType === 'tables_row') {
				activities = activities.filter((activity) => {
					return (activity.object_type === 'tables_row' && activity.object_id === this.objectId)
				})
			} else {
				activities = activities.filter((activity) => {
					return (activity.object_type === 'tables_table' && activity.object_id === this.objectId)
							|| (activity.object_type === 'tables_row' && activity.subject_rich[1].table.id === this.objectId.toString())
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
		async infiniteHandler($state) {
			await this.loadActivity()
			if (!this.endReached) {
				$state.loaded()
			} else {
				$state.complete()
			}
		},
		changeObject() {
			this.since = 0
			this.activities = []
			this.endReached = false
		},
	},
}
</script>

<style scoped>
.activity-list {
	margin-bottom: 100px;
}
</style>
