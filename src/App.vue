<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcContent app-name="tables">
		<Navigation />
		<NcAppContent>
			<div v-if="isLoadingSomething" class="icon-loading" />

			<router-view v-if="!isLoadingSomething" />
		</NcAppContent>
		<Sidebar />
	</NcContent>
</template>

<script>
import { NcContent, NcAppContent } from '@nextcloud/vue'
import Navigation from './modules/navigation/sections/Navigation.vue'
import { mapGetters } from 'vuex'
import Sidebar from './modules/sidebar/sections/Sidebar.vue'
import { useResizeObserver } from '@vueuse/core'
import { loadState } from '@nextcloud/initial-state'

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
			defaultPageTitle: false,
		}
	},
	computed: {
		...mapGetters(['isLoadingSomething']),
	},
	watch: {
		'$route'(to, from) {
			this.routing(to)
		},
	},
	async created() {
		await this.$store.dispatch('loadTablesFromBE')
		await this.$store.dispatch('getAllContexts')
		await this.$store.dispatch('loadViewsSharedWithMeFromBE')
		await this.$store.dispatch('loadTemplatesFromBE')
		this.routing(this.$router.currentRoute)
		this.observeAppContent()
	},
	methods: {
		routing(currentRoute) {
			try {
				if (loadState('tables', 'contextId', undefined)) {
					// prepare route, when Context is opened from navigation bar
					const contextId = loadState('tables', 'contextId', undefined)
					const originalUrl = window.location.href
					this.$router.replace('/application/' + contextId).catch(() => {})
					// reverts turning /apps/tables/app/28 into /apps/tables/app/28#/application/28
					history.replaceState({}, undefined, originalUrl)
				}
			} catch (e) {
				// contextId is not always set, it is fine.
			}

			if (currentRoute.name === 'tableRow' || currentRoute.name === 'viewRow') {
				this.$store.commit('setActiveRowId', parseInt(currentRoute.params.rowId))
			} else {
				this.$store.commit('setActiveRowId', null)
			}
			if (currentRoute.path.startsWith('/table/')) {
				this.$store.commit('setActiveTableId', parseInt(currentRoute.params.tableId))
				this.setPageTitle(this.$store.getters.activeTable.title)
				if (!currentRoute.path.includes('/row/')) {
					this.switchActiveMenuEntry(document.querySelector('header .header-left .app-menu a[title="Tables"]'))
				}
			} else if (currentRoute.path.startsWith('/view/')) {
				this.$store.commit('setActiveViewId', parseInt(currentRoute.params.viewId))
				this.setPageTitle(this.$store.getters.activeView.title)
				if (!currentRoute.path.includes('/row/')) {
					this.switchActiveMenuEntry(document.querySelector('header .header-left .app-menu a[title="Tables"]'))
				}
			} else if (currentRoute.path.startsWith('/application/')) {
				const contextId = parseInt(currentRoute.params.contextId)
				this.$store.commit('setActiveContextId', contextId)
				this.setPageTitle(this.$store.getters.activeContext.name)
				// This breaks if there are multiple contexts with the same name or another app has the same name. We need a better way to identify the correct element.
				this.switchActiveMenuEntry(document.querySelector(`header .header-left .app-menu [title="${this.$store.getters.activeContext.name}"]`))

				// move the focus away from nav bar (import for app-internal switch)
				const appContent = document.getElementById('app-content-vue')
				const oldTabIndex = appContent.tabIndex
				if (oldTabIndex === -1) {
					appContent.tabIndex = 0
				}
				appContent.focus()
				appContent.tabIndex = oldTabIndex
			}
		},
		switchActiveMenuEntry(targetElement) {
			targetElement = targetElement?.tagName?.toLowerCase() === 'a' ? targetElement.parentElement : targetElement
			const currentlyActive = document.querySelector('header .header-left .app-menu li.app-menu-entry--active')
			currentlyActive.classList.remove('app-menu-entry--active')
			targetElement.classList.add('app-menu-entry--active')
		},
		setPageTitle(title) {
			if (this.defaultPageTitle === false) {
				const appTitle = t('tables', 'Tables')
				this.defaultPageTitle = window.document.title
				if (this.defaultPageTitle.indexOf(` - ${appTitle} - `) !== -1) {
					this.defaultPageTitle = this.defaultPageTitle.substring(this.defaultPageTitle.indexOf(` - ${appTitle} - `) + 3)
				}
				if (this.defaultPageTitle.indexOf(`${appTitle} - `) !== 0) {
					this.defaultPageTitle = `${appTitle} - ` + this.defaultPageTitle
				}
			}
			let newTitle = this.defaultPageTitle
			if (title !== '') {
				newTitle = `${title} - ${newTitle}`
			}
			window.document.title = newTitle
		},
		observeAppContent() {
			useResizeObserver(document.getElementById('app-content-vue'), (entries) => {
				const entry = entries[0]
				const { width } = entry.contentRect
				document.documentElement.style.setProperty('--app-content-width', `${width}px`)
			})
		},
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

.inline-flex {
	display: inline-flex;
}

</style>
