<?php

namespace OCA\Tables\Service;

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

	public function __construct(LoggerInterface $logger, ?string $userId, TableMapper $tableMapper, ViewMapper $viewMapper, ShareMapper $shareMapper, UserHelper $userHelper, bool $isCLI) {
		$this->tableMapper = $tableMapper;
		$this->viewMapper = $viewMapper;
		$this->shareMapper = $shareMapper;
		$this->userHelper = $userHelper;
		$this->logger = $logger;
		$this->userId = $userId;
		$this->isCli = $isCLI;
	}


	/**
	 * @throws InternalError
	 */
	public function preCheckUserId(string $userId = null, bool $canBeEmpty = true): string {
		if ($userId === null) {
			$userId = $this->userId;
		}

		if ($userId === null) {
			$error = 'PreCheck for userId failed, requested in '. get_class($this) .'.';
			$this->logger->debug($error);
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

	public function canAccessView($view, ?string $userId = null): bool {
		try {
			if($this->basisCheck($view, 'view', $userId)) return true;
		} catch (InternalError $e) {
			return false;
		}

		try {
			$this->getSharedPermissionsIfSharedWithMe($view->getId(), 'view',  $userId);
			return true;
		} catch (InternalError|NotFoundError $e) {
		}

		return false;
	}

	/**
	 * @param Table|View $element
	 * @param string|null $userId
	 * @return bool
	 */
	public function canManageView(View $view, ?string $userId = null): bool {
        return $this->checkPermission($view, 'view', 'manage', $userId);
	}

	public function canManageTable(Table $table, ?string $userId = null): bool {
		return $this->checkPermission($table, 'table', 'manage', $userId);
	}

	public function canManageTableById(int $tableId, ?string $userId = null): bool {
		$table = $this->tableMapper->find($tableId);
		return $this->canManageTable($table, $userId);
	}


	// ***** COLUMNS permissions *****

	public function canReadColumnsByViewId(int $viewId, ?string $userId = null): bool {
		return $this->canReadRowsByElementId($viewId, 'view', $userId);
	}

	public function canReadColumnsByTableId(int $tableId, ?string $userId = null): bool {
		return $this->canReadRowsByElementId($tableId, 'table', $userId);
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
	 * @param string|null $userId
	 * @return bool
	 */
	public function canReadRowsByElementId(int $elementId, string $nodeType, ?string $userId = null): bool {
        return $this->checkPermissionById($elementId, $nodeType, 'read', $userId);
	}

	public function canReadRowsByElement($element, string $nodeType, ?string $userId = null): bool {
		return $this->checkPermission($element, $nodeType, 'read', $userId);
	}

	/**
	 * @param int $tableId
	 * @param string|null $userId
	 * @return bool
	 */
	public function canCreateRowsByViewId(int $viewId, ?string $userId = null): bool {
        return $this->checkPermissionById($viewId, 'view', 'create', $userId);
	}

	/**
	 * @param int $tableId
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
	public function canDeleteRowsByViewId(int $viewId, ?string $userId = null): bool {
        return $this->checkPermissionById($viewId, 'view', 'delete', $userId);
	}


	// ***** SHARE permissions *****

	/** @noinspection PhpUndefinedMethodInspection */
	public function canReadShare(Share $share, ?string $userId = null): bool {
		try {
			$userId = $this->preCheckUserId($userId);
		} catch (InternalError $e) {
			return false;
		}

		if ($userId === '') {
			return true;
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
				return false;
			}
		}

		return false;
	}

	public function canUpdateShare(Share $item, ?string $userId = null): bool {
		try {
			$userId = $this->preCheckUserId($userId);
		} catch (InternalError $e) {
			return false;
		}

		if ($userId === '') {
			return true;
		}

		/** @noinspection PhpUndefinedMethodInspection */
		return $item->getSender() === $userId;
	}

	public function canDeleteShare(Share $item, ?string $userId = null): bool {
		try {
			$userId = $this->preCheckUserId($userId);
		} catch (InternalError $e) {
			return false;
		}

		if ($userId === '') {
			return true;
		}

		/** @noinspection PhpUndefinedMethodInspection */
		return $item->getSender() === $userId;
	}

    public function getSharedPermissionsIfSharedWithMe(int $elementId, ?string $elementType = 'table', string $userId = null): array {
        $shares = $this->shareMapper->findAllSharesForNodeFor($elementType, $elementId, $userId, 'user');
        $userGroups = $this->userHelper->getGroupsForUser($userId);
        foreach ($userGroups as $userGroup) {
            $shares = array_merge($shares, $this->shareMapper->findAllSharesForNodeFor($elementType, $elementId, $userGroup->getGid(), 'group'));
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

    private function checkPermission($element, string $nodeType, string $permission, ?string $userId = null): bool {
        try {
            if($this->basisCheck($element, $nodeType, $userId)) return true;
        } catch (InternalError $e) {
            return false;
        }

        try {
            return $this->getSharedPermissionsIfSharedWithMe($element->getId(), $nodeType, $userId)[$permission];
        } catch (NotFoundError $e) {
        }
        return false;
    }

    private function checkPermissionById(int $elementId, string $nodeType, string $permission, ?string $userId = null): bool {
        try {
            if($this->basisCheckById($elementId, $nodeType, $userId)) return true;
        } catch (InternalError $e) {
            return false;
        }

        try {
            return $this->getSharedPermissionsIfSharedWithMe($elementId, $nodeType, $userId)[$permission];
        } catch (NotFoundError $e) {
        }
        return false;
    }

    /**
     * @param $element
     * @param string $nodeType
     * @param string|null $userId
     * @return bool
     * @throws InternalError
     */
    private function basisCheck($element, string $nodeType, ?string &$userId) {
        $userId = $this->preCheckUserId($userId);

        if ($userId === '') {
            return true;
        }

        if ($this->userIsElementOwner($userId, $element)) {
            return true;
        }
        try {
            $permissions = $this->getSharedPermissionsIfSharedWithMe($nodeType === 'view' ? $element->getTableId() : $element->getId(), 'table', $userId);
            if($permissions['manage']) {
                return true;
            }
        } catch (InternalError | NotFoundError $e) {
        }
        return false;
    }

    /**
     * @param int $elementId
     * @param string $nodeType
     * @param string|null $userId
     * @return bool
     * @throws DoesNotExistException
     * @throws Exception
     * @throws InternalError
     * @throws MultipleObjectsReturnedException
     * @throws NotFoundError
     */
    private function basisCheckById(int $elementId, string $nodeType, ?string &$userId) {
        $userId = $this->preCheckUserId($userId);

        if ($userId === '') {
            return true;
        }

        try {
            $element = $nodeType === 'table' ? $this->tableMapper->find($elementId) : $this->viewMapper->find($elementId);
            return $this->basisCheck($element, $nodeType, $userId);
        } catch (DoesNotExistException|MultipleObjectsReturnedException|\Exception $e) {
        }
        return false;
    }

    /** @noinspection PhpUndefinedMethodInspection */
	private function userIsElementOwner(string $userId, $element): bool {
		return $element->getOwnership() === $userId;
	}

	public function canChangeElementOwner($element, string $userId): bool {
		return $userId === '';
	}
}
