<template>
	<div v-if="canSort" class="sortIcon" :class="{ showOnHover: canSort && getSortMode === null}">
		<NcButton type="tertiary-no-background" :aria-label="t('tables', 'Sort this column')" @click="action">
			<template #icon>
				<SortAsc v-if="getSortMode === 'asc'" :size="20" />
				<SortDesc v-else-if="getSortMode === 'desc'" :size="20" />
				<SwapVertical v-else :size="20" />
			</template>
		</NcButton>
	</div>
	<div v-else class="dummy-box">
&nbsp;
	</div>
</template>

<script>
import textLineMixin from '../mixins/columnsTypes/textLineMixin.js'
import textLinkMixin from '../mixins/columnsTypes/textLinkMixin.js'
import selectionMixin from '../mixins/columnsTypes/selectionMixin.js'
import numberMixin from '../mixins/columnsTypes/numberMixin.js'
import selectionCheckMixin from '../mixins/columnsTypes/selectionCheckMixin.js'
import numberStarsMixin from '../mixins/columnsTypes/numberStarsMixin.js'
import numberProgressMixin from '../mixins/columnsTypes/numberProgressMixin.js'
import datetimeDateMixin from '../mixins/columnsTypes/datetimeDateMixin.js'
import datetimeTimeMixin from '../mixins/columnsTypes/datetimeTimeMixin.js'
import datetimeMixin from '../mixins/columnsTypes/datetimeMixin.js'
import generalHelper from '../../../mixins/generalHelper.js'
import SwapVertical from 'vue-material-design-icons/SwapVertical.vue'
import SortAsc from 'vue-material-design-icons/SortAscending.vue'
import SortDesc from 'vue-material-design-icons/SortDescending.vue'
import { NcButton } from '@nextcloud/vue'
import { mapState } from 'vuex'

export default {

	components: {
		SwapVertical,
		NcButton,
		SortAsc,
		SortDesc,
	},

	mixins: [
		textLineMixin,
		selectionMixin,
		numberMixin,
		generalHelper,
		selectionCheckMixin,
		textLinkMixin,
		numberStarsMixin,
		numberProgressMixin,
		datetimeDateMixin,
		datetimeTimeMixin,
		datetimeMixin,
	],

	props: {
		column: {
		      type: Object,
		      default: null,
		    },
	},

	computed: {
		...mapState({
			view: state => state.data.view,
		}),
		canSort() {
			const sortFuncName = 'sorting' + this.ucfirst(this.column?.type) + this.ucfirst(this.column?.subtype)
			if (this[sortFuncName] instanceof Function) {
				return true
			}
			console.info('no sort function for column found', { columnId: this.column.id, expectedSortMethod: sortFuncName })
			return false
		},
		getSortMode() {
			const sortObject = this.view.sorting?.find(item => item.columnId === this.column?.id)
			if (sortObject) {
				return sortObject.mode
			}
			return null
		},
	},

	methods: {
		action() {
			const sortMode = this.getSortMode === 'asc' ? 'desc' : 'asc'
			this.$store.dispatch('addSorting', { columnId: this.column.id, mode: sortMode })
		},
	},

}
</script>
<style lang="scss" scoped>

	.dummy-box {
		width: 44px;
		height: 44px;
	}

</style>
