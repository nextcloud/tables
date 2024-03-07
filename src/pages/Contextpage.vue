<!-- TODO figure out whether to keep this page, and how to structure it if yes -->

<template>
	<div class="main-context-view">
		<div class="container">
			<div class="first-row row">
				<h1> Contexts </h1>
			</div>
			<div v-for="context in contexts" :key="context.id" class="contexts-list row">
				<div class="context row">
					<NcActionRouter :to="'/context/' + parseInt(context.id)" :name="context.name" />
					<p>Description: {{ context.description }}</p>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { NcActionRouter } from '@nextcloud/vue'
import Vuex, { mapState } from 'vuex'
import Vue from 'vue'

Vue.use(Vuex)

export default {
	components: {
		NcActionRouter,
	},

	computed: {
		...mapState(['contexts']),
	},

}

</script>

<style scoped lang="scss">
.main-context-view {
	width: max-content;
	min-width: var(--app-content-width, 100%);
}

.context {
	width: 100%;
	display: block;
	margin: 30px;
}

.contexts-list {
	display: flex;
	flex-direction: column;
}
</style>
