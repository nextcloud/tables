/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/*
 * The menu of an application as the editor holds it: a flat, ordered list where a child carries
 * the key of its parent. A parent always precedes its children, there is one level of nesting,
 * and a parent that moves takes its children along. Every function returns a new list.
 */

// The same limits as MenuItemInput on the server
export const MAX_LABEL_LENGTH = 200
export const MAX_URL_LENGTH = 2000

/**
 * The payload the API expects for one menu item.
 *
 * @param {object} item an item from the editor
 * @param {Array} items the whole editable list, to turn the parent key into the parent's position
 * @return {object} the item as the API takes it
 */
export function toMenuItemPayload(item, items) {
	const parentIndex = item.parentKey ? items.findIndex(candidate => candidate.key === item.parentKey) : -1
	return {
		label: item.label,
		icon: item.icon || null,
		targetType: item.targetType,
		targetId: item.targetType === 'url' ? null : item.targetId,
		url: item.targetType === 'url' ? item.url : null,
		technicalName: item.technicalName || null,
		parentIndex: parentIndex >= 0 ? parentIndex : null,
	}
}

/**
 * A new, empty top-level item.
 *
 * @param {Array} items the current list, to give the item its own key
 * @return {object} the new item
 */
export function newMenuItem(items) {
	return {
		// Two items added within one millisecond still get their own key
		key: 'new-' + Date.now() + '-' + items.length,
		label: '',
		icon: null,
		targetType: 'url',
		targetId: null,
		url: '',
		technicalName: null,
		parentKey: null,
	}
}

/**
 * Whether a link is an http or https address with a host, as the server requires.
 *
 * @param {string|null} url the link
 * @return {boolean} true when the server accepts it
 */
export function isValidMenuLink(url) {
	if (typeof url !== 'string' || url.length > MAX_URL_LENGTH) {
		return false
	}
	try {
		const parsed = new URL(url.trim())
		return ['http:', 'https:'].includes(parsed.protocol) && parsed.hostname !== ''
	} catch {
		return false
	}
}

/**
 * What is missing or wrong in an item, by field, with the same rules as the server.
 *
 * @param {object} item an item from the editor
 * @return {{label: boolean, target: boolean, url: boolean}} true for every field that is invalid
 */
export function menuItemErrors(item) {
	const label = (item.label ?? '').trim()
	return {
		label: label === '' || label.length > MAX_LABEL_LENGTH,
		target: item.targetType !== 'url' && !(item.targetId > 0),
		url: item.targetType === 'url' && !isValidMenuLink(item.url),
	}
}

/**
 * Whether every item of the menu can be saved.
 *
 * @param {Array} items the editable list
 * @return {boolean} true when no item has an error
 */
export function areMenuItemsValid(items) {
	return items.every(item => !Object.values(menuItemErrors(item)).includes(true))
}

/**
 * The nearest top-level item above a position, which is the only possible parent.
 *
 * @param {Array} items the editable list
 * @param {number} index position of the item
 * @return {object|null} the possible parent, or null at the top of the list
 */
export function parentAbove(items, index) {
	for (let i = index - 1; i >= 0; i--) {
		if (items[i].parentKey === null) {
			return items[i]
		}
	}
	return null
}

/**
 * Whether an item has children.
 *
 * @param {Array} items the editable list
 * @param {object} item an item of the list
 * @return {boolean} true when another item sits under it
 */
export function hasChildren(items, item) {
	return items.some(candidate => candidate.parentKey === item.key)
}

/**
 * Whether an item can move under the top-level item above it: only one level of nesting.
 *
 * @param {Array} items the editable list
 * @param {number} index position of the item
 * @return {boolean} true when it can become a child
 */
export function canIndent(items, index) {
	return parentAbove(items, index) !== null && !hasChildren(items, items[index])
}

/**
 * Moves an item under the top-level item above it.
 *
 * @param {Array} items the editable list
 * @param {number} index position of the item
 * @return {Array} the new list, or the same list when the item cannot move
 */
export function indentItem(items, index) {
	if (!canIndent(items, index)) {
		return items
	}
	const parent = parentAbove(items, index)
	return items.map((item, i) => i === index ? { ...item, parentKey: parent.key } : item)
}

/**
 * Moves a child to the top level, right after its parent's last child.
 *
 * @param {Array} items the editable list
 * @param {number} index position of the child
 * @return {Array} the new list
 */
export function outdentItem(items, index) {
	const result = [...items]
	const [moved] = result.splice(index, 1)
	let insertAt = result.findIndex(item => item.key === moved.parentKey) + 1
	while (insertAt < result.length && result[insertAt].parentKey === moved.parentKey) {
		insertAt++
	}
	result.splice(insertAt, 0, { ...moved, parentKey: null })
	return result
}

/**
 * Removes an item; its children move to the top level instead of disappearing.
 *
 * @param {Array} items the editable list
 * @param {number} index position of the item
 * @return {Array} the new list
 */
export function removeItem(items, index) {
	const removed = items[index]
	return items
		.filter((item, i) => i !== index)
		.map(item => item.parentKey === removed.key ? { ...item, parentKey: null } : item)
}

/**
 * An item with its children, as one block that moves together.
 *
 * @param {Array} items the editable list
 * @param {number} index position of the item
 * @return {Array} the item followed by its children
 */
export function blockOf(items, index) {
	const item = items[index]
	if (item.parentKey !== null) {
		return [item]
	}
	return [item, ...items.filter(candidate => candidate.parentKey === item.key)]
}

/**
 * The items an item shares its level with: the top-level items, or the children of its parent.
 *
 * @param {Array} items the editable list
 * @param {object} item an item of the list
 * @return {Array} the items on the same level, in order
 */
export function siblingsOf(items, item) {
	return items.filter(candidate => candidate.parentKey === item.parentKey)
}

/**
 * Whether an item has a neighbour on its level in the given direction.
 *
 * @param {Array} items the editable list
 * @param {number} index position of the item
 * @param {number} step -1 to move up, 1 to move down
 * @return {boolean} true when it can move that way
 */
export function canMove(items, index, step) {
	const siblings = siblingsOf(items, items[index])
	return siblings[siblings.indexOf(items[index]) + step] !== undefined
}

/**
 * Swaps an item with its neighbour on the same level; a top-level item moves with its children.
 *
 * @param {Array} items the editable list
 * @param {number} index position of the item
 * @param {number} step -1 to move up, 1 to move down
 * @return {Array} the new list, or the same list when there is no neighbour
 */
export function moveItem(items, index, step) {
	const item = items[index]
	const siblings = siblingsOf(items, item)
	const neighbour = siblings[siblings.indexOf(item) + step]
	if (!neighbour) {
		return items
	}
	const [first, second] = step < 0 ? [neighbour, item] : [item, neighbour]
	const firstBlock = blockOf(items, items.indexOf(first))
	const secondBlock = blockOf(items, items.indexOf(second))
	const rest = items.filter(candidate => !firstBlock.includes(candidate) && !secondBlock.includes(candidate))
	rest.splice(items.indexOf(first), 0, ...secondBlock, ...firstBlock)
	return rest
}

/**
 * The parent an item inserted at a position would belong to, or null at the top level.
 *
 * @param {Array} items the list without the item being moved
 * @param {number} position where the item would be inserted
 * @return {object|null} the parent at that position
 */
export function parentOfPosition(items, position) {
	const previous = items[position - 1]
	if (!previous) {
		return null
	}
	if (previous.parentKey !== null) {
		return items.find(item => item.key === previous.parentKey) ?? null
	}
	const next = items[position]
	return next && next.parentKey === previous.key ? previous : null
}

/**
 * The list while an item is dragged over another one: a child joins the parent whose block it
 * lands in, and a top-level block stays top-level, behind the children it would land between.
 *
 * @param {Array} items the editable list
 * @param {number} draggedIndex position of the dragged item
 * @param {number} overIndex position of the item it is dragged over
 * @return {{items: Array, draggedIndex: number}|null} the new list and the dragged item's new position, or null when nothing changes
 */
export function dragItemOver(items, draggedIndex, overIndex) {
	if (draggedIndex === overIndex) {
		return null
	}
	const moving = blockOf(items, draggedIndex)
	const over = items[overIndex]
	if (moving.includes(over)) {
		return null
	}
	const rest = items.filter(item => !moving.includes(item))
	let insertAt = rest.indexOf(over)
	if (overIndex > draggedIndex) {
		insertAt += 1
	}
	const head = moving[0]
	const landingParent = parentOfPosition(rest, insertAt)
	const placed = head.parentKey !== null
		? [{ ...head, parentKey: landingParent?.key ?? null }]
		: moving
	if (head.parentKey === null && landingParent !== null) {
		while (insertAt < rest.length && rest[insertAt].parentKey !== null) {
			insertAt++
		}
	}
	rest.splice(insertAt, 0, ...placed)
	return { items: rest, draggedIndex: rest.indexOf(placed[0]) }
}
