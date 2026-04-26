/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'
import CircleOutline from 'vue-material-design-icons/CircleOutline.vue'
import FlagOutline from 'vue-material-design-icons/FlagOutline.vue'
import StarOutline from 'vue-material-design-icons/StarOutline.vue'
import { translate as t } from '@nextcloud/l10n'

export const MARKERS = {
	circle: { component: CircleOutline, label: () => t('tables', 'Circle') },
	star: { component: StarOutline, label: () => t('tables', 'Star') },
	flag: { component: FlagOutline, label: () => t('tables', 'Flag') },
	alert: { component: AlertCircleOutline, label: () => t('tables', 'Alert') },
	check: { component: CheckCircleOutline, label: () => t('tables', 'Check') },
}

/**
 * @param {string|null} marker the stored marker name
 * @return {object|null} the icon component to render, or null when there is none
 */
export function markerComponent(marker) {
	return MARKERS[marker]?.component ?? null
}
