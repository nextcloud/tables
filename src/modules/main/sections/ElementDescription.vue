<template>
	<div class="row first-row">
		<h1>
			{{ activeElement.emoji }}&nbsp;{{ activeElement.title }}
		</h1>
		<div class="info">
			<div v-if="isFiltered">
				<TextIcon :size="15" />
				{{ t('tables', 'Filtered view') }}&nbsp;&nbsp;
			</div>
			<NcSmallButton
				v-if="isViewSettingSet"
				@click="resetLocalAdjustments">
				<template #icon>
					<FilterRemove :size="15" />
				</template>
				{{ t('tables', 'Reset local adjustments') }}
			</NcSmallButton>
		</div>
		<div v-if="!isTable && activeElement.isShared" class="user-bubble">
			<NcUserBubble
				:display-name="activeElement.ownerDisplayName"
				:show-user-status="false"
				:user="activeElement.ownership" />
		</div>
	</div>
</template>

<script>

import { NcUserBubble } from '@nextcloud/vue'
import TextIcon from 'vue-material-design-icons/Text.vue'
import FilterRemove from 'vue-material-design-icons/FilterRemove.vue'
import NcSmallButton from '../../../shared/components/ncSmallButton/NcSmallButton.vue'

export default {
	name: 'ElementDescription',

	components: {
		NcUserBubble,
		TextIcon,
		FilterRemove,
		NcSmallButton,
	},

	props: {
		viewSetting: {
			type: Object,
			default: null,
		},
		activeElement: {
			type: Object,
			default: null,
		},
		isTable: {
			type: Boolean,
			default: false,
		},
	},
	computed: {
		isFiltered() {
			return this.activeElement.filter && this.activeElement.filter[0]?.length > 0
		},

		isViewSettingSet() {
			return !(!this.viewSetting || ((!this.viewSetting.hiddenColumns || this.viewSetting.hiddenColumns.length === 0) && (!this.viewSetting.sorting) && (!this.viewSetting.filter || this.viewSetting.filter.length === 0)))
		},
	},

	methods: {
		resetLocalAdjustments() {
			this.$emit('update:viewSetting', {})
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
	width: var(--app-content-width, auto);
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

.info > div {
	display: inline-flex;
	width: max-content;
}

.info span {
	padding: calc(var(--default-grid-baseline) * 1);
}

</style>
