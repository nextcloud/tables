<?php

namespace OCA\Tables\Middleware;

use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\PermissionsService;
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
		PermissionsService         $permissionsService,
		IRequest                   $request,
		?string                    $userId,
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
	public function beforeController($controller, $methodName): void {
		// we can have type hinting in the signature only after dropping NC26 – calling parent to enforce on newer releases
		parent::beforeController($controller, $methodName);
		$this->assertCanManageNode();
		$this->assertCanManageContext();
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
}
