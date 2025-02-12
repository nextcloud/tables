<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Middleware;

use InvalidArgumentException;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Helper\ConversionHelper;
use OCA\Tables\Middleware\Attribute\RequirePermission;
use OCA\Tables\Service\PermissionsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\Utility\IControllerMethodReflector;
use OCP\IRequest;

class PermissionMiddleware extends Middleware {
	private IControllerMethodReflector $reflector;
	private PermissionsService $permissionsService;
	private ?string $userId;
	private IRequest $request;

	public function __construct(
		IControllerMethodReflector $reflector,
		PermissionsService $permissionsService,
		IRequest $request,
		?string $userId,
	) {

		$this->reflector = $reflector;
		$this->permissionsService = $permissionsService;
		$this->userId = $userId;
		$this->request = $request;
	}

	/**
	 * @throws PermissionError
	 * @throws InternalError
	 */
	public function beforeController(Controller $controller, string $methodName) {
		$this->assertPermission($controller, $methodName);
		$this->assertCanManageNode();
		$this->assertCanManageContext();
	}

	protected function assertPermission(Controller $controller, string $methodName): void {
		$reflectionMethod = new \ReflectionMethod($controller, $methodName);
		$permissionReqs = $reflectionMethod->getAttributes(RequirePermission::class);
		if ($permissionReqs) {
			foreach ($permissionReqs as $permissionReqAttribute) {
				/** @var RequirePermission $attribute */
				$attribute = $permissionReqAttribute->newInstance();
				$this->checkPermission($attribute);
			}
		}
	}

	/**
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	protected function checkPermission(RequirePermission $attribute): void {
		$nodeId = $this->request->getParam($attribute->getIdParam());
		if (!is_numeric($nodeId)) {
			throw new InternalError('Invalid node ID');
		}
		$nodeId = (int)$nodeId;

		$nodeType = $attribute->getType() ?? $this->request->getParam($attribute->getTypeParam());
		$isContext = false;
		if (!is_numeric($nodeType)) {
			if ($nodeType === 'context') {
				// contexts are not considered nodes, but we deal with them as well
				// currently they are only passed as string as well
				$isContext = true;
			} else {
				try {
					$nodeType = ConversionHelper::stringNodeType2Const((string)$nodeType);
				} catch (InvalidArgumentException) {
					throw new InternalError('Invalid node type');
				}
			}
		} elseif (!in_array((int)$nodeType, [Application::NODE_TYPE_TABLE, Application::NODE_TYPE_VIEW], true)) {
			throw new InternalError('Invalid node type');
		}
		$nodeType = (int)$nodeType;

		// pre-test: if the node is not accessible in first place, we do not have to reveal it
		// this is also an assertion for READ permissions.
		if ($isContext) {
			if (!$this->permissionsService->canAccessContextById($nodeId, $this->userId)) {
				throw new NotFoundError();
			}
		} else {
			if (!$this->permissionsService->canAccessNodeById($nodeType, $nodeId, $this->userId)) {
				throw new NotFoundError();
			}
		}

		match ($attribute->getPermission()) {
			Application::PERMISSION_READ => true, // this is guaranteed in the pre-test ^
			Application::PERMISSION_MANAGE => $this->assertManagePermission($isContext, $nodeType, $nodeId),
			Application::PERMISSION_CREATE => $this->assertCreatePermissions($nodeType, $nodeId),
			Application::PERMISSION_UPDATE => $this->assertUpdatePermissions($nodeType, $nodeId),
			Application::PERMISSION_DELETE => $this->assertDeletePermissions($nodeType, $nodeId),
			Application::PERMISSION_ALL => $this->assertManagePermission($isContext, $nodeType, $nodeId)
				&& $this->assertCreatePermissions($nodeType, $nodeId)
				&& $this->assertUpdatePermissions($nodeType, $nodeId)
				&& $this->assertDeletePermissions($nodeType, $nodeId),
		};
	}

	/**
	 * @throws PermissionError
	 */
	private function assertCreatePermissions(int $nodeType, int $nodeId): bool {
		if (!$this->permissionsService->canCreateRowsById($nodeType, $nodeId, $this->userId)) {
			throw new PermissionError(sprintf('User %s cannot create rows on node %d (type %d)',
				$this->userId, $nodeId, $nodeType
			));
		}
		return true;
	}

	/**
	 * @throws PermissionError
	 */
	private function assertUpdatePermissions(int $nodeType, int $nodeId): bool {
		if (
			($nodeType === Application::NODE_TYPE_TABLE && !$this->permissionsService->canUpdateRowsByTableId($nodeId, $this->userId))
			|| ($nodeType === Application::NODE_TYPE_VIEW && !$this->permissionsService->canUpdateRowsByViewId($nodeId, $this->userId))
		) {
			throw new PermissionError(sprintf('User %s cannot update rows on node %d (type %d)',
				$this->userId, $nodeId, $nodeType
			));
		}
		return true;
	}

	/**
	 * @throws PermissionError
	 */
	private function assertDeletePermissions(int $nodeType, int $nodeId): bool {
		if (
			($nodeType === Application::NODE_TYPE_TABLE && !$this->permissionsService->canDeleteRowsByTableId($nodeId, $this->userId))
			|| ($nodeType === Application::NODE_TYPE_VIEW && !$this->permissionsService->canDeleteRowsByViewId($nodeId, $this->userId))
		) {
			throw new PermissionError(sprintf('User %s cannot delete rows on node %d (type %d)',
				$this->userId, $nodeId, $nodeType
			));
		}
		return true;
	}

	/**
	 * @throws PermissionError
	 */
	private function assertManagePermission(bool $isContext, int $nodeType, int $nodeId): bool {
		if (!$isContext) {
			if (!$this->permissionsService->canManageNodeById($nodeType, $nodeId, $this->userId)) {
				throw new PermissionError(sprintf('User %s cannot manage node %d (type %d)',
					$this->userId, $nodeId, $nodeType
				));
			}
		} else {
			if (!$this->permissionsService->canManageContextById($nodeId, $this->userId)) {
				throw new PermissionError(sprintf('User %s cannot manage context %d',
					$this->userId, $nodeId
				));
			}
		}
		return true;
	}

	/**
	 * @throws PermissionError
	 * @throws InternalError
	 */
	protected function assertCanManageNode(): void {
		if ($this->reflector->hasAnnotation('CanManageNode')) {
			$nodeId = $this->request->getParam('nodeId');
			$nodeType = $this->request->getParam('nodeType');

			if (!is_numeric($nodeId) || !is_numeric($nodeType)) {
				throw new InternalError('Cannot identify node');
			}

			if ($this->userId === null) {
				throw new PermissionError('User not authenticated');
			}

			if (!$this->permissionsService->canManageNodeById((int)$nodeType, (int)$nodeId, $this->userId)) {
				throw new PermissionError(sprintf('User %s cannot manage node %d (type %d)',
					$this->userId, (int)$nodeId, (int)$nodeType
				));
			}
		}
	}

	/**
	 * @throws PermissionError
	 * @throws InternalError
	 */
	protected function assertCanManageContext(): void {
		if ($this->reflector->hasAnnotation('CanManageContext')) {
			$contextId = $this->request->getParam('contextId');

			if (!is_numeric($contextId)) {
				throw new InternalError('Cannot identify context');
			}

			if ($this->userId === null) {
				throw new PermissionError('User not authenticated');
			}

			if (!$this->permissionsService->canManageContextById((int)$contextId, $this->userId)) {
				throw new PermissionError(sprintf('User %s cannot manage context %d',
					$this->userId, (int)$contextId
				));
			}
		}
	}

	public function afterException($controller, $methodName, \Exception $exception) {
		if ($exception instanceof PermissionError) {
			return new Http\DataResponse(['message' => $exception->getMessage()], Http::STATUS_FORBIDDEN);
		}
		if ($exception instanceof NotFoundError) {
			return new Http\DataResponse(['message' => $exception->getMessage()], Http::STATUS_NOT_FOUND);
		}
		throw $exception;
	}
}
