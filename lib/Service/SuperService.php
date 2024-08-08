<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use Psr\Log\LoggerInterface;

class SuperService {
	protected PermissionsService $permissionsService;

	protected LoggerInterface $logger;

	protected ?string $userId;

	public function __construct(LoggerInterface $logger, ?string $userId, PermissionsService $permissionsService) {
		$this->permissionsService = $permissionsService;
		$this->logger = $logger;
		$this->userId = $userId;
	}
}
