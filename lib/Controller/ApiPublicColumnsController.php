<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Controller;

use InvalidArgumentException;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Middleware\Attribute\AssertShareAccessIsAccessible;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\ShareService;
use OCA\Tables\Service\ValueObject\ShareToken;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TablesPublicColumn from ResponseDefinitions
 */
class ApiPublicColumnsController extends ACommonColumnsOCSController {

	public function __construct(
		protected ColumnService $service,
		protected ShareService $shareService,
		IRequest $request,
		LoggerInterface $logger,
		IL10N $l,
	) {
		parent::__construct($request, $logger, $l, '');
	}

	/**
	 * [api v2] Get all columns for a table or a view shared by link
	 *
	 * Return an empty array if no columns were found
	 *
	 * @param string $token The share token
	 * @return DataResponse<Http::STATUS_OK, list<TablesPublicColumn>, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Columns are returned
	 * 400: Invalid request parameters
	 * 403: No permissions
	 * 404: Not found
	 * 500: Internal error
	 */
	#[PublicPage]
	#[AssertShareAccessIsAccessible]
	#[ApiRoute(verb: 'GET', url: '/api/2/public/{token}/columns', requirements: ['token' => '[a-zA-Z0-9]{16}'])]
	#[OpenAPI]
	#[AnonRateLimit(limit: 10, period: 10)]
	public function indexByPublicLink(string $token): DataResponse {
		try {
			$shareToken = new ShareToken($token);
		} catch (InvalidArgumentException $e) {
			return $this->handleBadRequestError(new BadRequestError(
				'Invalid share token',
				$e->getCode(),
				$e
			));
		}

		try {
			$share = $this->shareService->findByToken($shareToken);
			$columns = $this->getColumnsFromTableOrView($share->getNodeType(), $share->getNodeId(), '');

			$formattedTableColumns = $this->service->formatColumnsForPublicShare($columns);
			return new DataResponse($formattedTableColumns);
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (InternalError $e) {
			return $this->handleError($e);
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		} catch (BadRequestError $e) {
			return $this->handleBadRequestError($e);
		}
	}
}
