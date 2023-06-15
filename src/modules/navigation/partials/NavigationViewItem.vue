<template>
	<NcAppNavigationItem
		:name="view.title"
		:to="'/view/' + parseInt(view.id)">
		<template #icon>
			<template v-if="view.emoji">
				{{ view.emoji }}
			</template>
			<template v-else>
				<Table :size="20" />
			</template>
		</template>
	</NcAppNavigationItem>
</template>
<script>
import { NcAppNavigationItem } from '@nextcloud/vue'
import '@nextcloud/dialogs/dist/index.css'
// import { mapGetters } from 'vuex'
import Table from 'vue-material-design-icons/Table.vue'
import permissionsMixin from '../../../shared/components/ncTable/mixins/permissionsMixin.js'

export default {
	name: 'NavigationViewItem',

	components: {
		// eslint-disable-next-line vue/no-reserved-component-names
		Table,
		NcAppNavigationItem,
	},

	filters: {
		truncate(string, num) {
			if (string.length >= num) {
				return string.substring(0, num) + '...'
			} else {
				return string
			}
		},
	},

	mixins: [permissionsMixin],

	props: {
		view: {
			type: Object,
			default: null,
		},
	},

	// computed: {
	// 	...mapGetters(['activeTable']),
	// },

}
</script>
<style lang="scss">

.app-navigation-entry__counter-wrapper {
	button.action-button {
		padding-right: 0;
	}

	.counter-bubble__counter {
		display: none;
	}
	margin-right: 0 !important;
}

.app-navigation-entry {
	.margin-right {
		margin-right: 44px;
	}
	.margin-left {
		margin-left: calc(var(--default-grid-baseline) * 2);
	}
}

.app-navigation-entry:hover {
	.margin-right {
		margin-right: 0;
	}

	.app-navigation-entry__counter-wrapper .counter-bubble__counter {
		display: inline-flex;
	}
}

</style>
