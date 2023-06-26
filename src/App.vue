<template>
	<NcContent app-name="tables">
		<Navigation />
		<NcAppContent>
			<div v-if="somethingIsLoading" class="icon-loading" />

			<router-view v-if="!somethingIsLoading" />
		</NcAppContent>
		<Sidebar />
	</NcContent>
</template>

<script>
import { NcContent, NcAppContent } from '@nextcloud/vue'
import Navigation from './modules/navigation/sections/Navigation.vue'
import { mapGetters, mapState } from 'vuex'
import Sidebar from './modules/sidebar/sections/Sidebar.vue'

export default {
	name: 'App',
	components: {
		Sidebar,
		NcContent,
		NcAppContent,
		Navigation,
	},
	props: {
		tableId: {
			type: Number,
			default: null,
		},
	},
	data() {
		return {
			loading: false,
		}
	},
	computed: {
		...mapState(['tablesLoading']),
		// ...mapState(['tables', 'tablesLoading']),
		// ...mapGetters(['activeTable']),
		somethingIsLoading() {
			return this.tablesLoading || this.loading
		},
	},
	watch: {
		'$route'(to, from) {
			if (to.name === 'table') {
				this.$store.commit('setActiveTableId', parseInt(to.params.tableId))
			} else if (to.name === 'view') {
				this.$store.commit('setActiveViewId', parseInt(to.params.viewId))
			}
			// console.debug("Setting table ID to value",to.params.tableId, to)
		},
	},
	async created() {
		await this.$store.dispatch('loadTablesFromBE')
		await this.$store.dispatch('loadViewsSharedWithMeFromBE')
		const $currentRoute = this.$router.currentRoute
		if ($currentRoute.name === 'table') {
			this.$store.commit('setActiveTableId', parseInt($currentRoute.params.tableId))
		} else if ($currentRoute.name === 'view') {
			this.$store.commit('setActiveViewId', parseInt($currentRoute.params.viewId))
		}
	},
}
</script>
<style scoped lang="scss">

	.sidebar-icon {
		position: absolute;
		right: 5px;
		top: 5px;
	}

</style>
<style lang="scss">

	h1 {
		font-size: 1.98em;
	}

	h2 {
		font-size: larger;
	}

	h3 {
		font-size: large;
	}

	h4 {
		font-size: medium;
		font-weight: 300;
	}

	p, .p {
		padding-top: 5px;
		padding-bottom: 7px;
	}

	.editor-wrapper p, .editor-wrapper .p {
		padding: 0;
	}

	p span, .p span, p .span, .p .span, .p.span, p.span, .light {
		color: var(--color-text-maxcontrast);
	}

	p code {
		white-space: pre-wrap;
		background-color: var(--color-background-dark);
		border-radius: var(--border-radius);
		padding: 0.8em 1em;
		margin-bottom: 0.8em;
		font-family: 'Lucida Console', 'Lucida Sans Typewriter', 'DejaVu Sans Mono', monospace;
	}

	.bold {
		font-weight: bold;
	}

	.light {
		font-weight: 100;
		color: var(--color-text-lighter);
		/*
      background-color: var(--color-background-hover);
      */
	}

	button[class^='icon-'] {
		min-width: 36px !important;
	}

	[class^='col-'] > span {
		color: var(--color-text-maxcontrast);
	}

	.icon-left {
		background-position: left;
		padding-left: 22px;
	}

	.mandatory {
		font-weight: bold;
	}

	.v-popover button {
		height: 25px;
		margin-left: 10px;
		background-color: transparent;
		border: none;
	}

	.popover__inner p, .v-popper__inner table {
		padding: 15px;
	}

	.v-popper__inner table td {
		padding-right: 15px;
	}

	.error {
		color:  var(--color-error);
	}

	.error input {
		border-color: var(--color-error);
	}

	.icon-loading:first-child {
		top: 10vh;
	}

	.block {
		display: block !important;
	}

	.align-right {
		text-align: right;
	}

</style>
