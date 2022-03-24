import { mapGetters } from 'vuex'

export default {
	components: {
		...mapGetters(['activeTable']),
	},
	methods: {
		print() {
			this.$refs.tabulator.getInstance().print('all', true, {})
		},
	},
}
