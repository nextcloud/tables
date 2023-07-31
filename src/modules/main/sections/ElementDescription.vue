<template>
	<div class="row first-row">
		<h1>
			{{ activeView.emoji }}&nbsp;{{ activeView.title }}
		</h1>
		<div v-if="isFiltered" class="info">
			<FilterMultipleOutline :size="20" />
			{{ t('tables', 'Filtered view') }}
		</div>
		<div v-if="activeView.isShared" class="user-bubble">
			<NcUserBubble
				:display-name="activeView.ownerDisplayName"
				:show-user-status="true"
				:user="activeView.ownership" />
		</div>
	</div>
</template>

<script>

import { mapGetters } from 'vuex'
import { emit } from '@nextcloud/event-bus'
import { NcUserBubble } from '@nextcloud/vue'
import FilterMultipleOutline from 'vue-material-design-icons/FilterMultipleOutline.vue'

export default {
	name: 'ElementDescription',
	components: {
		NcUserBubble,
		FilterMultipleOutline,
	},
	computed: {
		...mapGetters(['activeView']),
		isFiltered() {
			return this.activeView.filter && this.activeView.filter[0]?.length > 0
		},
	},

	methods: {
		editElement() {
			emit('tables:view:edit', this.activeView)
		},
	},
}
</script>

<style lang="scss" scoped>

.light {
	opacity: .3;
}

.first-row:hover .light {
	opacity: 1;
}

.row.first-row {
	position: sticky;
	left: 0;
	top: 0;
	z-index: 15;
	background-color: var(--color-main-background-translucent);
	align-items: center;
}

.user-bubble {
	padding-left: calc(var(--default-grid-baseline) * 2);
}

.info {
	display: inline-flex;
	margin-left: calc(var(--default-grid-baseline) * 2);
	align-items: center;
	color: var(--color-text-maxcontrast);
}

.info span {
	padding: calc(var(--default-grid-baseline) * 1);
}

</style>
