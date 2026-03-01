<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Controller;

use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\ColumnService;

abstract class ACommonColumnsOCSController extends AOCSController {
	protected ColumnService $service;

	/**
	 * @throws PermissionError
	 * @throws NotFoundError
	 * @throws InternalError
	 * @throws BadRequestError
	 */
	protected function getColumnsFromTableOrView(string $nodeType, int $nodeId, ?string $overriddenUserid = null): array {
		if ($nodeType === 'table') {
			return $this->service->findAllByTable($nodeId, $overriddenUserid);
		}
		if ($nodeType === 'view') {
			return $this->service->findAllByView($nodeId, $overriddenUserid);
		}
		throw new BadRequestError('Invalid node type provided');
	}
}
