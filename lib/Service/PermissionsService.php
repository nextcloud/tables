<?php

namespace OCA\Tables\Service;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\Context;
use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Db\Share;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Helper\UserHelper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use Psr\Log\LoggerInterface;

class PermissionsService {
	private TableMapper $tableMapper;

	private ViewMapper $viewMapper;

	private ShareMapper $shareMapper;

	private UserHelper $userHelper;

	protected LoggerInterface $logger;

	protected ?string $userId = null;

	protected bool $isCli = false;
	private ContextMapper $contextMapper;

	public function __construct(
		LoggerInterface $logger,
		?string         $userId,
		TableMapper     $tableMapper,
		ViewMapper      $viewMapper,
		ShareMapper     $shareMapper,
		ContextMapper   $contextMapper,
		UserHelper      $userHelper,
		bool            $isCLI
	) {
		$this->tableMapper = $tableMapper;
		$this->viewMapper = $viewMapper;
		$this->shareMapper = $shareMapper;
		$this->userHelper = $userHelper;
		$this->logger = $logger;
		$this->userId = $userId;
		$this->isCli = $isCLI;
		$this->contextMapper = $contextMapper;
	}


	/**
	 * @param string|null $userId
	 * @param bool $canBeEmpty
	 * @return string
	 *
	 * @throws InternalError
	 */
	public function preCheckUserId(string $userId = null, bool $canBeEmpty = true): string {
		if ($userId === null) {
			$userId = $this->userId;
		}

		if ($userId === null) {
			$e = new \Exception();
			$error = 'PreCheck for userId failed, requested in '. get_class($this) .'.';
			$this->logger->debug($error, ['exception' => new \Exception()]);
			throw new InternalError($error);
		}

		if ($userId === '' && !$this->isCli && !$canBeEmpty) {
			$error = 'Try to set no user in context, but request is not allowed.';
			$this->logger->warning($error);
			throw new InternalError($error);
		}
		return $userId;
	}


	// ***** TABLES permissions *****

	public function canReadTable(Table $table, ?string $userId = null): bool {
		return $this->canReadColumnsByTableId($table->getId(), $userId);
	}

	/**
	 * @param Table $table
	 * @param string|null $userId
	 * @return bool
	 */
	public function canUpdateTable(Table $table, ?string $userId = null): bool {
		try {
			$userId = $this->preCheckUserId($userId);
		} catch (InternalError $e) {
			return false;
		}

		if ($userId === '') {
			return true;
		}

		return $this->canManageTable($table, $userId);
	}

	public function canAccessNodeById(int $nodeType, int $nodeId, ?string $userId = null): bool {
		if ($nodeType === Application::NODE_TYPE_TABLE) {
			return $this->canReadColumnsByTableId($nodeId, $userId);
		}
		if ($nodeType === Application::NODE_TYPE_VIEW) {
			return $this->canReadColumnsByViewId($nodeId, $userId);
		}

		return false;
	}

	public function canManageNodeById(int $nodeType, int $nodeId, ?string $userId = null): bool {
		if ($nodeType === Application::NODE_TYPE_TABLE) {
			return $this->canManageTableById($nodeId, $userId);
		}
		if ($nodeType === Application::NODE_TYPE_VIEW) {
			return $this->canManageViewById($nodeId, $userId);
		}

		return false;
	}

	public function canManageContextById(int $contextId, ?string $userId = null): bool {
		try {
			$context = $this->contextMapper->findById($contextId, $userId);
		} catch (DoesNotExistException $e) {
			$this->logger->warning('Context does not exist');
			return false;
		} catch (MultipleObjectsReturnedException $e) {
			$this->logger->warning('Multiple contexts found for this ID');
			return false;
		} catch (Exception $e) {
			$this->logger->warning($e->getMessage());
			return false;
		}

		if ($context->getOwnerType() !== Application::OWNER_TYPE_USER) {
			$this->logger->warning('Unsupported owner type');
			return false;
		}

		return $context->getOwnerId() === $userId || $this->canManageContext($context, $userId);
	}

	/**
	 * @throws Exception
	 */
	public function canAccessContextById(int $contextId, ?string $userId = null): bool {
		try {
			$this->contextMapper->findById($contextId, $userId ?? $this->userId);
			return true;
		} catch (NotFoundError $e) {
			return false;
		}
	}

	public function canAccessView(View $view, ?string $userId = null): bool {
		return $this->canAccessNodeById(Application::NODE_TYPE_VIEW, $view->getId(), $userId);
	}

	/**
	 * @param int $elementId
	 * @param string $nodeType
	 * @param string|null $userId
	 * @return bool
	 * @throws InternalError
	 * @note prefer canManageNodeById()
	 */
	public function canManageElementById(int $elementId, string $nodeType = 'table', ?string $userId = null): bool {
		if ($nodeType === 'table') {
			return $this->canManageTableById($elementId, $userId);
		} elseif ($nodeType === 'view') {
			return $this->canManageViewById($elementId, $userId);
		} elseif ($nodeType === 'context') {
			return $this->canManageContextById($elementId, $userId);
		} else {
			throw new InternalError('Cannot read permission for node type '.$nodeType);
		}
	}

	/**
	 * @param View $view
	 * @param string|null $userId
	 * @return bool
	 */
	public function canManageView(View $view, ?string $userId = null): bool {
		return $this->checkPermission($view, 'view', 'manage', $userId);
	}

	public function canManageTable(Table $table, ?string $userId = null): bool {
		return $this->checkPermission($table, 'table', 'manage', $userId);
	}

	public function canManageContext(Context $context, ?string $userId = null): bool {
		return $this->checkPermission($context, 'context', 'manage', $userId);
	}

	public function canManageTableById(int $tableId, ?string $userId = null): bool {
		try {
			$table = $this->tableMapper->find($tableId);
		} catch (MultipleObjectsReturnedException $e) {
			$this->logger->warning('Multiple tables were found for this id');
			return false;
		} catch (DoesNotExistException $e) {
			$this->logger->warning('No table was found for this id');
			return false;
		} catch (Exception $e) {
			$this->logger->warning('Error occurred: '.$e->getMessage());
			return false;
		}
		return $this->canManageTable($table, $userId);
	}

	public function canManageViewById(int $viewId, ?string $userId = null): bool {
		try {
			$view = $this->viewMapper->find($viewId);
		} catch (MultipleObjectsReturnedException $e) {
			$this->logger->warning('Multiple tables were found for this id');
			return false;
		} catch (DoesNotExistException $e) {
			$this->logger->warning('No table was found for this id');
			return false;
		} catch (InternalError | Exception $e) {
			$this->logger->warning('Error occurred: '.$e->getMessage());
			return false;
		}
		return $this->canManageView($view, $userId);
	}


	// ***** COLUMNS permissions *****

	public function canReadColumnsByViewId(int $viewId, ?string $userId = null): bool {
		return $this->canReadRowsByElementId($viewId, 'view', $userId);
	}

	public function canReadColumnsByTableId(int $tableId, ?string $userId = null): bool {
		$canReadRows = $this->checkPermissionById($tableId, 'table', 'read', $userId);
		$canCreateRows = $this->checkPermissionById($tableId, 'table', 'create', $userId);
		return $canCreateRows || $canReadRows;
	}

	/**
	 * @param Table $table
	 * @param string|null $userId
	 * @return bool
	 */
	public function canCreateColumns(Table $table, ?string $userId = null): bool {
		return $this->canManageTable($table, $userId);
	}

	/**
	 * @param int $tableId
	 * @param string|null $userId
	 * @return bool
	 */
	public function canUpdateColumnsByTableId(int $tableId, ?string $userId = null): bool {
		return $this->canManageTableById($tableId, $userId);
	}

	/**
	 * @param int $tableId
	 * @param string|null $userId
	 * @return bool
	 */
	public function canDeleteColumnsByTableId(int $tableId, ?string $userId = null): bool {
		return $this->canManageTableById($tableId, $userId);
	}


	// ***** ROWS permissions *****


	/**
	 * @param int $elementId
	 * @param 'table'|'view' $nodeType
	 * @param string|null $userId
	 * @return bool
	 */
	public function canReadRowsByElementId(int $elementId, string $nodeType, ?string $userId = null): bool {
		return $this->checkPermissionById($elementId, $nodeType, 'read', $userId);
	}

	/**
	 * @param Table|View $element
	 * @param 'table'|'view' $nodeType
	 * @param string|null $userId
	 * @return bool
	 */
	public function canReadRowsByElement($element, string $nodeType, ?string $userId = null): bool {
		return $this->checkPermission($element, $nodeType, 'read', $userId);
	}

	/**
	 * @param Table|View $element
	 * @param 'table'|'view' $nodeType
	 * @param string|null $userId
	 * @return bool
	 */
	public function canCreateRows($element, string $nodeType = 'view', ?string $userId = null): bool {
		if ($nodeType === 'table') {
			return $this->checkPermission($element, 'table', 'create', $userId);
		}
		return $this->checkPermission($element, 'view', 'create', $userId);
	}

	/**
	 * @param int $viewId
	 * @param string|null $userId
	 * @return bool
	 */
	public function canUpdateRowsByViewId(int $viewId, ?string $userId = null): bool {
		return $this->checkPermissionById($viewId, 'view', 'update', $userId);
	}

	/**
	 * @param int $tableId
	 * @param string|null $userId
	 * @return bool
	 */
	public function canUpdateRowsByTableId(int $tableId, ?string $userId = null): bool {
		return $this->checkPermissionById($tableId, 'table', 'update', $userId);
	}


	/**
	 * @param int $viewId
	 * @param string|null $userId
	 * @return bool
	 */
	public function canDeleteRowsByViewId(int $viewId, ?string $userId = null): bool {
		return $this->checkPermissionById($viewId, 'view', 'delete', $userId);
	}

	/**
	 * @param int|null $tableId
	 * @param string|null $userId
	 * @return bool
	 */
	public function canDeleteRowsByTableId(int $tableId = null, ?string $userId = null): bool {
		if ($tableId === null) {
			return false;
		}
		return $this->checkPermissionById($tableId, 'table', 'delete', $userId);

	}


	// ***** SHARE permissions *****

	public function canReadShare(Share $share, ?string $userId = null): bool {
		try {
			$userId = $this->preCheckUserId($userId);
		} catch (InternalError $e) {
			$this->logger->warning('Cannot pre check the user id, permission denied');
			return false;
		}

		if ($userId === '') {
			return true;
		}
		try {
			if ($this->canManageElementById($share->getNodeId(), $share->getNodeType())) {
				return true;
			}
		} catch (InternalError $e) {
			$this->logger->warning('Cannot check manage permissions, permission denied');
			return false;
		}


		if ($share->getSender() === $userId) {
			return true;
		}

		if ($share->getReceiverType() === 'user' && $share->getReceiver() === $userId) {
			return true;
		}

		if ($share->getReceiverType() === 'group') {
			try {
				$userGroups = $this->userHelper->getGroupsForUser($userId);
				foreach ($userGroups as $userGroup) {
					if ($userGroup->getDisplayName() === $share->getReceiver()) {
						return true;
					}
				}
			} catch (InternalError $e) {
				$this->logger->warning('Cannot get user groups, permission denied');
				return false;
			}
		}

		return false;
	}

	/**
	 * @param int $elementId
	 * @param 'table'|'view' $elementType
	 * @param string $userId
	 * @return array
	 * @throws NotFoundError
	 */
	public function getSharedPermissionsIfSharedWithMe(int $elementId, string $elementType, string $userId): array {
		try {
			$shares = $this->shareMapper->findAllSharesForNodeFor($elementType, $elementId, $userId);
		} catch (Exception $e) {
			$this->logger->warning('Exception occurred: '.$e->getMessage().' Permission denied.');
			return [];
		}

		try {
			$userGroups = $this->userHelper->getGroupsForUser($userId);
		} catch (InternalError $e) {
			$this->logger->warning('Exception occurred: '.$e->getMessage().' Permission denied.');
			return [];
		}
		foreach ($userGroups as $userGroup) {
			try {
				$shares = array_merge($shares, $this->shareMapper->findAllSharesForNodeFor($elementType, $elementId, $userGroup->getGid(), 'group'));
			} catch (Exception $e) {
				$this->logger->warning('Exception occurred: '.$e->getMessage().' Permission denied.');
				return [];
			}
		}
		if (count($shares) > 0) {
			$read = array_reduce($shares, function ($carry, $share) {
				return $carry || ($share->getPermissionRead());
			}, false);
			$create = array_reduce($shares, function ($carry, $share) {
				return $carry || ($share->getPermissionCreate());
			}, false);
			$update = array_reduce($shares, function ($carry, $share) {
				return $carry || ($share->getPermissionUpdate());
			}, false);
			$delete = array_reduce($shares, function ($carry, $share) {
				return $carry || ($share->getPermissionDelete());
			}, false);
			$manage = array_reduce($shares, function ($carry, $share) {
				return $carry || ($share->getPermissionManage());
			}, false);

			return [
				'read' => $read || $update || $delete || $manage,
				'create' => $create || $manage,
				'update' => $update || $manage,
				'delete' => $delete || $manage,
				'manage' => $manage,
			];
		}
		throw new NotFoundError('No share for '.$elementType.' and given user ID found.');
	}

	//  private methods ==========================================================================

	/**
	 * @throws NotFoundError
	 */
	public function getPermissionIfAvailableThroughContext(int $nodeId, string $nodeType, string $userId): int {
		$permissions = 0;
		$found = false;
		$iNodeType = match ($nodeType) {
			'table' => Application::NODE_TYPE_TABLE,
			'view' => Application::NODE_TYPE_VIEW,
		};
		$contexts = $this->contextMapper->findAllContainingNode($iNodeType, $nodeId, $userId);
		foreach ($contexts as $context) {
			$found = true;
			if ($context->getOwnerType() === Application::OWNER_TYPE_USER
				&& $context->getOwnerId() === $userId) {
				// Making someone owner of a context, makes this person also having manage permissions on the node.
				// This is sort of an intended "privilege escalation".
				return Application::PERMISSION_ALL;
			}
			foreach ($context->getNodes() as $nodeRelation) {
				$permissions |= $nodeRelation['permissions'];
			}
		}
		if (!$found) {
			throw new NotFoundError('Node not found in any context');
		}
		return $permissions;
	}

	/**
	 * @throws NotFoundError
	 */
	public function getPermissionArrayForNodeFromContexts(int $nodeId, string $nodeType, string $userId) {
		$permissions = $this->getPermissionIfAvailableThroughContext($nodeId, $nodeType, $userId);
		return [
			'read' => (bool)($permissions & Application::PERMISSION_READ),
			'create' => (bool)($permissions & Application::PERMISSION_CREATE),
			'update' => (bool)($permissions & Application::PERMISSION_UPDATE),
			'delete' => (bool)($permissions & Application::PERMISSION_DELETE),
			'manage' => (bool)($permissions & Application::PERMISSION_MANAGE),
		];
	}

	private function hasPermission(int $existingPermissions, string $permissionName): bool {
		$constantName = 'PERMISSION_' . strtoupper($permissionName);
		try {
			$permissionBit = constant(Application::class . "::$constantName");
		} catch (\Throwable $t) {
			$this->logger->error('Unexpected permission string {permission}', [
				'app' => Application::APP_ID,
				'permission' => $permissionName,
				'exception' => $t,
			]);
			return false;
		}
		return (bool)($existingPermissions & $permissionBit);
	}

	/**
	 * @param Table|View|Context $element
	 * @param 'table'|'view'|'context' $nodeType
	 * @param string $permission
	 * @param string|null $userId
	 * @return bool
	 */
	private function checkPermission(Table|View|Context $element, string $nodeType, string $permission, ?string $userId = null): bool {
		if($this->basisCheck($element, $nodeType, $userId)) {
			return true;
		}

		if (!$userId) {
			return false;
		}

		try {
			return $this->getSharedPermissionsIfSharedWithMe($element->getId(), $nodeType, $userId)[$permission];
		} catch (NotFoundError $e) {
			try {
				if ($nodeType !== 'context'
					&& $this->hasPermission($this->getPermissionIfAvailableThroughContext($element->getId(), $nodeType, $userId), $permission)
				) {
					return true;
				}
			} catch (NotFoundError $e) {
			}
			$this->logger->error($e->getMessage(), ['exception' => $e]);
		}

		return false;
	}

	/**
	 * @param int $elementId
	 * @param 'table'|'view' $nodeType
	 * @param string $permission
	 * @param string|null $userId
	 * @return bool
	 */
	private function checkPermissionById(int $elementId, string $nodeType, string $permission, ?string $userId = null): bool {
		if($this->basisCheckById($elementId, $nodeType, $userId)) {
			return true;
		}
		if ($userId) {
			try {
				return $this->getSharedPermissionsIfSharedWithMe($elementId, $nodeType, $userId)[$permission];
			} catch (NotFoundError $e) {
				try {
					if ($this->hasPermission($this->getPermissionIfAvailableThroughContext($elementId, $nodeType, $userId), $permission)) {
						return true;
					}
				} catch (NotFoundError $e) {
				}
				$this->logger->error($e->getMessage(), ['exception' => $e]);
			}
		}
		return false;
	}

	private function basisCheck(Table|View|Context $element, string $nodeType, ?string &$userId): bool {
		try {
			$userId = $this->preCheckUserId($userId);
		} catch (InternalError $e) {
			$e = new \Exception('Cannot pre check the user id');
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return false;
		}

		if ($userId === '') {
			return true;
		}

		if ($this->userIsElementOwner($element, $userId, $nodeType)) {
			return true;
		}
		try {
			$permissions = $this->getSharedPermissionsIfSharedWithMe($nodeType === 'view' ? $element->getTableId() : $element->getId(), 'table', $userId);
			if($permissions['manage']) {
				return true;
			}
		} catch (NotFoundError $e) {
			return false;
		}
		return false;
	}

	/**
	 * @param int $elementId
	 * @param string $nodeType
	 * @param string|null $userId
	 * @return bool
	 */
	private function basisCheckById(int $elementId, string $nodeType, ?string &$userId): bool {
		try {
			$userId = $this->preCheckUserId($userId);
		} catch (InternalError $e) {
			$this->logger->warning('Cannot pre check the user id');
		}

		if ($userId === '') {
			return true;
		}

		try {
			$element = $nodeType === 'table' ? $this->tableMapper->find($elementId) : $this->viewMapper->find($elementId);
			return $this->basisCheck($element, $nodeType, $userId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|\Exception $e) {
			$this->logger->warning('Exception occurred: '.$e->getMessage());
		}
		return false;
	}

	/**
	 * @param View|Table|Context $element
	 * @param string|null $userId
	 * @return bool
	 */
	private function userIsElementOwner($element, string $userId = null, ?string $nodeType = null): bool {
		if ($nodeType === 'context') {
			return $element->getOwnerId() === $userId;
		}
		return $element->getOwnership() === $userId;
	}

	/**
	 * @param View|Table $element
	 * @param string $userId
	 * @return bool
	 */
	public function canChangeElementOwner($element, string $userId): bool {
		return $userId === '' || $this->userIsElementOwner($element, $userId);
	}
}
