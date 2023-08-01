<template>
	<div class="row first-row">
		<h1>
			{{ activeView.emoji }}&nbsp;{{ activeView.title }}
		</h1>
		<div v-if="isFiltered" class="info">
			<TextIcon :size="15" />
			{{ t('tables', 'Filtered view') }}&nbsp;&nbsp;
			<NcSmallButton v-if="isViewSettingSet">
				🔙 {{ t('tables', 'Reset local adjustments') }}
			</NcSmallButton>
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
import TextIcon from 'vue-material-design-icons/Text.vue'
import NcSmallButton from '../../../shared/components/ncSmallButton/NcSmallButton.vue'

export default {
	name: 'ElementDescription',

	components: {
		NcUserBubble,
		TextIcon,
		NcSmallButton,
	},

	props: {
		viewSetting: {
			type: Object,
			default: null,
		},
	},

	computed: {
		...mapGetters(['activeView']),
		isFiltered() {
			return this.activeView.filter && this.activeView.filter[0]?.length > 0
		},

		isViewSettingSet() {
			return !(!this.viewSetting || ((!this.viewSetting.hiddenColumns || this.viewSetting.hiddenColumns.length === 0) && (!this.viewSetting.sorting) && (!this.viewSetting.filter || this.viewSetting.filter.length === 0)))
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
