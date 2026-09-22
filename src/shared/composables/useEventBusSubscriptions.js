/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { onBeforeUnmount } from 'vue'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'

/**
 * Subscribes to the Nextcloud event bus and removes every subscription on unmount.
 *
 * `unsubscribe()` matches handlers by identity, so passing a freshly created closure to it never
 * removes anything. Keeping the original reference and reusing it here makes that mismatch
 * impossible.
 *
 * @return {{subscribeToEventBus: (name: string, handler: (event: unknown) => void) => void}} Subscription helper
 */
export function useEventBusSubscriptions() {
	const subscriptions = []

	onBeforeUnmount(() => {
		for (const [name, handler] of subscriptions) {
			unsubscribe(name, handler)
		}
		subscriptions.length = 0
	})

	return {
		subscribeToEventBus(name, handler) {
			subscribe(name, handler)
			subscriptions.push([name, handler])
		},
	}
}
