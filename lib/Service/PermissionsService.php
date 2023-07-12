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

	/**
	 * @param Table|View $element
	 * @param string|null $userId
	 * @return bool
	 */
	public function canReadElement($element, string $nodeType, ?string $userId = null): bool {
		try {
			if($this->basisCheck($element, $userId)) return true;
		} catch (InternalError $e) {
			return false;
		}

		try {
			$share = $this->getShareForElement($element, $nodeType,  $userId);
			return !!$share->getPermissionRead();
		} catch (InternalError|NotFoundError $e) {
		}

		return false;
	}

	/**
	 * @param Table|View $element
	 * @param string|null $userId
	 * @return bool
	 */
	public function canUpdateElement($element, string $nodeType, ?string $userId = null): bool {
		try {
			if($this->basisCheck($element, $userId)) return true;
		} catch (InternalError $e) {
			return false;
		}

		try {
			$share = $this->getShareForElement($element, $nodeType, $userId);
			/** @noinspection PhpUndefinedMethodInspection */
			return !!$share->getPermissionUpdate();
		} catch (InternalError|NotFoundError $e) {
		}

		return false;
	}

	/**
	 * @param Table|View $element
	 * @param string|null $userId
	 * @return bool
	 */
	public function canDeleteElement($element, string $nodeType, ?string $userId = null): bool {
		try {
			if($this->basisCheck($element, $userId)) return true;
		} catch (InternalError $e) {
			return false;
		}

		try {
			$share = $this->getShareForElement($element, $nodeType, $userId);
			/** @noinspection PhpUndefinedMethodInspection */
			return !!$share->getPermissionDelete();
		} catch (InternalError|NotFoundError $e) {
		}

		return false;
	}


	/**
	 * @param Table|View $element
	 * @param string|null $userId
	 * @return bool
	 */
	public function canManageElement($element, string $nodeType, ?string $userId = null): bool {
		try {
			if($this->basisCheck($element, $userId)) return true;
		} catch (InternalError $e) {
			return false;
		}

		try {
			$share = $this->getShareForElement($element, $nodeType, $userId);
			/** @noinspection PhpUndefinedMethodInspection */
			return !!$share->getPermissionManage();
		} catch (InternalError|NotFoundError $e) {
		}

		return false;
	}

	/**
	 * @param $element
	 * @param string $userId
	 * @return bool
	 * @throws InternalError
	 */
	public function basisCheck($element, ?string &$userId) {
		$userId = $this->preCheckUserId($userId);

		if ($userId === '') {
			return true;
		}

		if ($this->userIsElementOwner($userId, $element)) {
			return true;
		}
		return false;
	}


	// ***** COLUMNS permissions *****

	public function canReadColumnsByTableId(int $tableId, ?string $userId = null): bool {
		try {
			$table = $this->tableMapper->find($tableId);
			// if you can read the table, you also can read its columns
			return $this->canReadElement($table, 'table', $userId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
		}
		return false;
	}

	public function canReadColumnsByViewId(int $viewId, ?string $userId = null): bool {
		try {
			$view = $this->viewMapper->find($viewId);
			// if you can read the table, you also can read its columns
			return $this->canReadElement($view, 'view', $userId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
		}
		return false;
	}

	/**
	 * @param Table $table
	 * @param string|null $userId
	 * @return bool
	 */
	public function canCreateColumns(Table $table, ?string $userId = null): bool {
		// this is the same permission as to update a table
		return $this->canManageElement($table, 'table', $userId);
	}

	/**
	 * @param int $tableId
	 * @param string|null $userId
	 * @return bool
	 */
	public function canCreateColumnsByTableId(int $tableId, ?string $userId = null): bool {
		try {
			$table = $this->tableMapper->find($tableId);
			return $this->canCreateColumns($table, $userId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
		}
		return false;
	}

	/**
	 * @param int $tableId
	 * @param string|null $userId
	 * @return bool
	 */
	public function canUpdateColumnsByTableId(int $tableId, ?string $userId = null): bool {
		try {
			$table = $this->tableMapper->find($tableId);
			// this is the same permission as to update a table
			return $this->canManageElement($table, 'table', $userId);
		} catch (\Exception $e) {
		}
		return false;
	}

	/**
	 * @param int $tableId
	 * @param string|null $userId
	 * @return bool
	 */
	public function canDeleteColumnsByTableId(int $tableId, ?string $userId = null): bool {
		try {
			$table = $this->tableMapper->find($tableId);
			// this is the same permission as to update a table
			return $this->canManageElement($table, 'table', $userId);
		} catch (\Exception $e) {
		}
		return false;
	}


	// ***** ROWS permissions *****


	/**
	 * @param int $elementId
	 * @param string|null $userId
	 * @return bool
	 */
	public function canReadRowsByElementId(int $elementId, string $nodeType, ?string $userId = null): bool {
		try {
			$userId = $this->preCheckUserId($userId);
		} catch (InternalError $e) {
			return false;
		}

		if ($userId === '') {
			return true;
		}

		try {
			$element = $nodeType === 'table' ? $this->tableMapper->find($elementId) : $this->viewMapper->find($elementId);

			if ($this->userIsElementOwner($userId, $element)) {
				return true;
			}

			try {
				$share = $this->getShareForElement($element, $nodeType, $userId);
				/** @noinspection PhpUndefinedMethodInspection */
				return !!$share->getPermissionRead();
			} catch (InternalError|NotFoundError $e) {
			}
		} catch (DoesNotExistException|MultipleObjectsReturnedException|\Exception $e) {
		}

		return false;
	}

	/**
	 * @param int $tableId
	 * @param string|null $userId
	 * @return bool
	 */
	public function canCreateRowsByViewId(int $viewId, ?string $userId = null): bool {
		try {
			$userId = $this->preCheckUserId($userId);
		} catch (InternalError $e) {
			return false;
		}

		if ($userId === '') {
			return true;
		}

		try {
			$view = $this->viewMapper->find($viewId);

			if ($this->userIsElementOwner($userId, $view)) {
				return true;
			}

			try {
				$share = $this->getShareForElement($view, 'view', $userId);
				/** @noinspection PhpUndefinedMethodInspection */
				return !!$share->getPermissionCreate();
			} catch (InternalError|NotFoundError $e) {
			}
		} catch (\Exception $e) {
		}

		return false;
	}

	/**
	 * @param int $tableId
	 * @param string|null $userId
	 * @return bool
	 */
	public function canUpdateRowsByViewId(int $viewId, ?string $userId = null): bool {
		try {
			$userId = $this->preCheckUserId($userId);
		} catch (InternalError $e) {
			return false;
		}

		if ($userId === '') {
			return true;
		}

		try {
			$view = $this->viewMapper->find($viewId);

			if ($this->userIsElementOwner($userId, $view)) {
				return true;
			}

			try {
				$share = $this->getShareForElement($view, 'view', $userId);
				/** @noinspection PhpUndefinedMethodInspection */
				return !!$share->getPermissionUpdate();
			} catch (InternalError|NotFoundError $e) {
			}
		} catch (\Exception $e) {
		}

		return false;
	}

	/**
	 * @param int $tableId
	 * @param string|null $userId
	 * @return bool
	 */
	public function canDeleteRowsByViewId(int $viewId, ?string $userId = null): bool {
		try {
			$userId = $this->preCheckUserId($userId);
		} catch (InternalError $e) {
			return false;
		}

		if ($userId === '') {
			return true;
		}

		try {
			$view = $this->viewMapper->find($viewId);

			if ($this->userIsElementOwner($userId, $view)) {
				return true;
			}

			try {
				$share = $this->getShareForElement($view, 'view', $userId);
				/** @noinspection PhpUndefinedMethodInspection */
				return !!$share->getPermissionDelete() || !!$share->getPermissionManage();
			} catch (InternalError|NotFoundError $e) {
			}
		} catch (\Exception $e) {
		}

		return false;
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


	//  private methods ==========================================================================

	/**
	 * @param Table|View $element
	 * @param string $userId
	 * @param string $nodeType
	 * @return Share
	 * @throws InternalError
	 * @throws NotFoundError
	 */
	private function getShareForElement($element, string $nodeType, string $userId): Share {
		// if shared by user
		try {
			return $this->shareMapper->findShareForNode($element->getId(), $nodeType, $userId, 'user');
		} catch (Exception $e) {
		}

		// if shared by group
		try {
			$userGroups = $this->userHelper->getGroupsForUser($userId);
		} catch (InternalError $e) {
			$this->logger->error('could not fetch user data for permission check');
			throw new InternalError('could not fetch user data for permission check');
		}
		foreach ($userGroups as $userGroup) {
			try {
				return $this->shareMapper->findShareForNode($element->getId(), $nodeType, $userGroup->getGID(), 'group');
			} catch (Exception $e) {
			}
		}
		throw new NotFoundError('no share found');
	}

	/** @noinspection PhpUndefinedMethodInspection */
	private function userIsElementOwner(string $userId, $element): bool {
		return $element->getOwnership() === $userId;
	}

	public function canChangeElementOwner($element, string $userId): bool {
		return $userId === '';
	}
}
