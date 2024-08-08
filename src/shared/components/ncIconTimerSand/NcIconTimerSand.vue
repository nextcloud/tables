<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="icon-timer-sand">
		<IconTimerSand v-if="sandIcon === 0" :size="20" />
		<IconTimerSandPaused v-if="sandIcon === 1" :size="20" />
		<IconTimerSandComplete v-if="sandIcon === 2" :size="20" />
	</div>
</template>
<script>

import IconTimerSandPaused from 'vue-material-design-icons/TimerSandPaused.vue'
import IconTimerSandComplete from 'vue-material-design-icons/TimerSandComplete.vue'
import IconTimerSand from 'vue-material-design-icons/TimerSand.vue'

export default {
	components: {
		IconTimerSand,
		IconTimerSandComplete,
		IconTimerSandPaused,
	},

	props: {
		interval: {
			type: Number,
			default: 500, // milliseconds
		},
	},

	data() {
		return {
			sandIcon: 0,
			sandIconTimer: null,
			sandIconFactor: -1,
		}
	},

	computed: {
	},

	mounted() {
		this.updateSandIcon()
	},

	beforeDestroy() {
		clearTimeout(this.sandIconTimer)
	},

	methods: {
		updateSandIcon() {
			if (this.sandIcon === 2 || this.sandIcon === 0) {
				this.sandIconFactor = this.sandIconFactor * -1
			}
			this.sandIcon = this.sandIcon + this.sandIconFactor
			this.sandIconTimer = setTimeout(this.updateSandIcon, this.interval)
		},
	},

}
</script>
