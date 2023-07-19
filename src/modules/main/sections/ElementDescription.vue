<template>
	<div class="row first-row">
		<h1>
			{{ activeView.emoji }}&nbsp;{{ activeView.title }}
		</h1>
		<div v-if="!activeView.isShared || (activeView.isShared && activeView.onSharePermissions.manage)" class="light">
			<NcButton
				:aria-label="activeView.isBaseView ? t('tables', 'Edit table') : t('tables', 'Edit view')"
				type="tertiary"
				@click="editElement">
				<template #icon>
					<Pencil :size="20" />
				</template>
				{{ activeView.isBaseView ? t('tables', 'Edit table') : t('tables', 'Edit view') }}
			</NcButton>
		</div>
		<div v-if="activeView.isShared" class="user-bubble">
			<NcUserBubble
				:display-name="activeView.ownerDisplayName"
				:show-user-status="true"
				:user="activeView.ownership" />
		</div>
	</div>
</template>

<script>

import { mapGetters } from 'vuex'
import { emit } from '@nextcloud/event-bus'
import { NcButton, NcUserBubble } from '@nextcloud/vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
export default {
	name: 'ElementDescription',
	components: {
		NcButton,
		NcUserBubble,
		Pencil,
	},
	computed: {
		...mapGetters(['activeView']),
	},

	methods: {
		editElement() {
			emit('tables:view:edit', this.activeView)
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

</style>
