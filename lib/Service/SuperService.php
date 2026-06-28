<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use Psr\Log\LoggerInterface;

class SuperService {
	protected bool $isPublicContext = false;

	public function __construct(protected LoggerInterface $logger, protected ?string $userId, protected PermissionsService $permissionsService)
    {
    }

	public function setPublicContext(): void {
		$this->userId = '';
		$this->isPublicContext = true;
		$this->permissionsService->setPublicContext();
	}
}
