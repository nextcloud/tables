<template>
	<div class="row first-row">
		<h1>
			{{ activeTable.emoji }}&nbsp;{{ activeTable.title }}
		</h1>
	</div>
</template>

<script>
import { mapGetters } from 'vuex'

export default {
	name: 'TableDescription',
	computed: {
		...mapGetters(['activeTable']),
	},
}
</script>
