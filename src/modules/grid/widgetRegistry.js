/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import HeaderWidget from './widgets/HeaderWidget.vue'
import TextWidget from './widgets/TextWidget.vue'
import DataWidget from './widgets/DataWidget.vue'

export const WIDGET_TYPE_HEADER = 'header'
export const WIDGET_TYPE_TEXT = 'text'
export const WIDGET_TYPE_DATA = 'data'

/**
 * The component that renders each widget type. Everything else about a type,
 * its title, default size and the schema of its content, comes from the server
 * (see GridWidgetTypes.php) so the form and the validation share one definition.
 */
const widgetComponents = {
	[WIDGET_TYPE_HEADER]: HeaderWidget,
	[WIDGET_TYPE_TEXT]: TextWidget,
	[WIDGET_TYPE_DATA]: DataWidget,
}

/**
 * @param {string} type widget type
 * @return {object|null} the component that renders it
 */
export function getWidgetComponent(type) {
	return widgetComponents[type] ?? null
}
