<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use Psr\Log\LoggerInterface;

class SuperService {
	public function __construct(protected LoggerInterface $logger, protected ?string $userId, protected PermissionsService $permissionsService)
    {
    }
}
