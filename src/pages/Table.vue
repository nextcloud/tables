<template>
	<div class="main-table-view">
		<MainWrapper :element="activeTable" :is-view="false" />
		<MainModals />
	</div>
</template>

<script>

import { mapGetters, mapState } from 'vuex'
import MainWrapper from '../modules/main/sections/MainWrapper.vue'
import MainModals from '../modules/modals/Modals.vue'

export default {
	components: {
		MainWrapper,
		MainModals,
	},

	computed: {
		...mapState(['activeTableId']),
		...mapGetters(['activeTable']),
	},

	watch: {
		activeTableId() {
			if (this.activeTableId && !this.activeTable) {
				// table does not exists, go to startpage
				this.$router.push('/').catch(err => err)
			}
		},
		activeTable() {
			if (this.activeTableId && !this.activeTable) {
				// table does not exists, go to startpage
				this.$router.push('/').catch(err => err)
			}
		},
	},
}
</script>
<style lang="scss">
.main-table-view {
	width: max-content;
	min-width: var(--app-content-width, 100%);
}

@page {
	size: auto;
	margin: 5mm;
}

@media print {
	html, body {
		background: var(--color-main-background, white) !important;
	}

	html {
		overflow-y: scroll;
	}

	body {
		position: absolute;
	}

	/* hide toast notifications for printing */
	.toastify.dialogs {
		display: none;
	}

	.app-navigation {
		display: none !important;
	}

	#header {
		display: none !important;
	}

	#content-vue {
		display: block !important;
		position: static;
		overflow: auto;
		height: auto;
	}

	.main-table-view {
		width: auto;
		min-width: 0;
	}

	.main-table-view table {
		table-layout: fixed;
	}

	.main-table-view table td, table th{
		white-space: normal !important;
		word-break: normal !important;
		word-wrap: break-word !important;
	}
}
</style>
