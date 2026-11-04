/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export default {
	data() {
		return {
			draggedItem: null,
			startDragIndex: null,
		}
	},

	computed: {
		/**
		 * @return {Array} the list this mixin mutates in place
		 */
		reorderableList() {
			return []
		},
	},

	methods: {
		dragStart(index) {
			this.draggedItem = this.reorderableList[index]
			this.startDragIndex = index
		},

		dragOver(index) {
			if (this.draggedItem === null) {
				return
			}
			const draggedIndex = this.reorderableList.indexOf(this.draggedItem)
			if (draggedIndex === -1 || index === draggedIndex) {
				return
			}
			this.reorderableList.splice(draggedIndex, 1)
			this.reorderableList.splice(index, 0, this.draggedItem)
		},

		dragEnd(goalIndex) {
			if (this.draggedItem === null) {
				return
			}
			const goal = goalIndex ?? this.reorderableList.indexOf(this.draggedItem)
			const moved = this.startDragIndex !== goal
			this.draggedItem = null
			this.startDragIndex = null
			if (moved) {
				this.onReorderCommitted()
			}
		},

		onReorderCommitted() {},
	},
}
