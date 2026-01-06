<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<div v-if="loading" class="icon-loading" />
		<ul v-if="mutableResources && mutableResources.length > 0" class="resource-list">
			<li v-for="(resource, index) in mutableResources" :key="resource.key" class="row" draggable="true"
				@dragstart="dragStart(index)" @dragover.prevent="dragOver(index)" @dragend="dragEnd(index)">
				<div class="fix-col-2">
					<div style="display:flex; align-items: center; width: 100%;">
						<NcButton :aria-label="t('tables', 'Move')" type="tertiary-no-background" class="move-button">
							<template #icon>
								<DragHorizontalVariant :size="20" />
							</template>
						</NcButton>
						{{ resource.emoji }} &nbsp; {{ resource.title }}
					</div>
				</div>
				<div class="fix-col-2" style="justify-content: end;">
					<NcActionButton :close-after-click="true" icon="icon-delete" @click="actionDelete(resource)">
						{{ t('tables', 'Delete') }}
					</NcActionButton>
				</div>
			</li>
		</ul>
		<div v-else>
			{{ t('tables', 'No selected resources') }}
		</div>
	</div>
</template>

<script>
import { NcActionButton, NcButton } from '@nextcloud/vue'
import DragHorizontalVariant from 'vue-material-design-icons/DragHorizontalVariant.vue'

export default {
	components: {
		NcActionButton,
		NcButton,
		DragHorizontalVariant,
	},

	props: {
		resources: {
			type: Array,
			default: () => ([]),
		},
	},

	data() {
		return {
			loading: false,
			mutableResources: [],
			draggedItem: null,
			startDragIndex: null,
		}
	},

	watch: {
		resources: {
			handler(newResources) {
				this.mutableResources = [...newResources]
			},
			deep: true,
			immediate: true,
		},
	},

	methods: {
		actionDelete(resource) {
			this.$emit('remove', resource)
		},
		dragStart(index) {
			this.draggedItem = this.mutableResources[index]
			this.startDragIndex = index
		},
		dragOver(index) {
			if (this.draggedItem === null) return
			const draggedIndex = this.mutableResources.indexOf(this.draggedItem)
			if (index !== draggedIndex) {
				this.mutableResources.splice(draggedIndex, 1)
				this.mutableResources.splice(index, 0, this.draggedItem)
				this.$emit('update:resources', this.mutableResources)
			}
		},
		dragEnd(goalIndex) {
			if (this.draggedItem === null) return
			const goal = goalIndex !== undefined ? goalIndex : this.mutableResources.indexOf(this.draggedItem)
			if (this.startDragIndex === goal) {
				this.draggedItem = null
				this.startDragIndex = null
				return
			}
			this.draggedItem = null
			this.startDragIndex = null
			this.$emit('update:resources', this.mutableResources)
		},
	},
}
</script>

<style lang="scss" scoped>
.resource-list li {
	display: flex;
	justify-content: space-between;
	line-height: 44px;
}

.resource-label {
	font-style: italic;
}

.move-button {
	cursor: move !important;
	padding-right: 10px;
}
</style>
