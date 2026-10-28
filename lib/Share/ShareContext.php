<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Share;

use OCA\Tables\Constants\ShareReceiverType;
use OCA\Tables\Db\Share;

class ShareContext {
	private ?Share $share = null;

	public function setShare(Share $share): void {
		$this->share = $share;
	}

	public function getRemoteUserId(): ?string {
		if ($this->share?->getReceiverType() !== ShareReceiverType::REMOTE) {
			return null;
		}

		return $this->share->getReceiver();
	}
}
