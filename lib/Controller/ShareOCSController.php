<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Helper\ConversionHelper;
use OCA\Tables\Middleware\Attribute\RequirePermission;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Service\ShareService;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\ViewService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Security\Events\ValidatePasswordPolicyEvent;
use OCP\Security\PasswordContext;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TablesLinkShare from ResponseDefinitions
 */
class ShareOCSController extends AOCSController
{
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		IL10N $n,
		string $userId,
		protected ShareService $shareService,
		protected TableService $tableService,
		protected ViewService $viewService,
		protected IEventDispatcher $eventDispatcher,
		protected IURLGenerator $urlGenerator,
	) {
		parent::__construct($request, $logger, $n, $userId);
	}

	/**
	 * [api v2] Create a new link share of a table or view
	 *
	 * @param 'tables'|'views' $nodeCollection Indicates whether to create a row on a table or view
	 * @param int $nodeId The identifier of the targeted table or view
	 * @param ?string $password (Optional) A password to protect the link share with
	 * @return DataResponse<Http::STATUS_OK, TablesLinkShare, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Link share created
	 * 400: Invalid request parameters
	 * 403: No permissions
	 * 404: Not found
	 * 500: Internal error
	 */
	#[NoAdminRequired]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, typeParam: 'nodeCollection')]
	#[ApiRoute(verb: 'POST', url: '/api/2/{nodeCollection}/{nodeId}/share')]
	#[UserRateLimit(limit: 20, period: 600)]
	public function createLinkShare(
		string $nodeCollection,
		int $nodeId,
		?string $password = null,
	): DataResponse {
		try {
			$collection = ConversionHelper::stringNodeType2Const($nodeCollection);
			if ($collection === Application::NODE_TYPE_TABLE) {
				$node = $this->tableService->find($nodeId);
			} else {
				$node = $this->viewService->find($nodeId);
			}
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		} catch (InternalError $e) {
			return $this->handleError($e);
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		}

		if ($password !== null) {
			$event = new ValidatePasswordPolicyEvent($password, PasswordContext::SHARING);
			try {
				$this->eventDispatcher->dispatchTyped($event);
			} catch (\Exception $e) {
				$error = new BadRequestError($e->getMessage(), $e->getCode(), $e);
				return $this->handleBadRequestError($error);
			}
		}

		try {
			$share = $this->shareService->createLinkShare($node, $password);
		} catch (InternalError $e) {
			return $this->handleError($e);
		}

		return new DataResponse([
			'shareToken' => $share->getToken(),
			'url' => $this->urlGenerator->linkToRouteAbsolute('tables.page.linkShare', ['token' => $share->getToken()]),
		]);
	}
}
