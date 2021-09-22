<template>
	<div id="content">
		<Navigation />
		<AppContent>
			<div>CONTENT</div>
		</AppContent>
	</div>
</template>

<script>
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import Navigation from './Navigation'
import '@nextcloud/dialogs/styles/toast.scss'

export default {
	name: 'App',
	components: {
		AppContent,
		Navigation,
	},
}
</script>
