<?php

namespace OCA\Tables\Service;

use OCA\Tables\Db\Share;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Helper\UserHelper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use Psr\Log\LoggerInterface;

class PermissionsService {
	private TableMapper $tableMapper;

	private ShareMapper $shareMapper;

	private UserHelper $userHelper;

	protected LoggerInterface $logger;

	protected ?string $userId = null;

	protected bool $isCli = false;

	public function __construct(LoggerInterface $logger, ?string $userId, TableMapper $tableMapper, ShareMapper $shareMapper, UserHelper $userHelper, bool $isCLI) {
		$this->tableMapper = $tableMapper;
		$this->shareMapper = $shareMapper;
		$this->userHelper = $userHelper;
		$this->logger = $logger;
		$this->userId = $userId;
		$this->isCli = $isCLI;
	}


	/**
	 * @throws InternalError
	 */
	public function preCheckUserId(string $userId = null, bool $canBeEmpty = true): string
	{
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
	 * @param Table $table
	 * @param string|null $userId
	 * @return bool
	 */
	public function canReadTable(Table $table, ?string $userId = null): bool {
		try {
			$userId = $this->preCheckUserId($userId);
		} catch (InternalError $e) {
			return false;
		}

		if ($userId === '') {
			return true;
		}

		/** @var string $userId */
		if ($this->userIsTableOwner($userId, $table)) {
			return true;
		}

		try {
			$this->getShareForTable($table, $userId);
			return true;
		} catch (InternalError|NotFoundError $e) {
		}

		return false;
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

		/** @var string $userId */
		if ($this->userIsTableOwner($userId, $table)) {
			return true;
		}

		try {
			$share = $this->getShareForTable($table, $userId);
			/** @noinspection PhpUndefinedMethodInspection */
			return !!$share->getPermissionManage();
		} catch (InternalError|NotFoundError $e) {
		}

		return false;
	}

	/**
	 * @param Table $table
	 * @param string|null $userId
	 * @return bool
	 */
	public function canDeleteTable(Table $table, ?string $userId = null): bool {
		try {
			$userId = $this->preCheckUserId($userId);
		} catch (InternalError $e) {
			return false;
		}

		if ($userId === '') {
			return true;
		}

		/** @var string $userId */
		if ($this->userIsTableOwner($userId, $table)) {
			return true;
		}

		return false;
	}


	// ***** COLUMNS permissions *****

	public function canReadColumnsByTableId(int $tableId, ?string $userId = null): bool {
		try {
			$table = $this->tableMapper->find($tableId);
			// if you can read the table, you also can read its columns
			return $this->canReadTable($table, $userId);
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
		return $this->canUpdateTable($table, $userId);
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
			return $this->canUpdateTable($table, $userId);
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
			return $this->canUpdateTable($table, $userId);
		} catch (\Exception $e) {
		}
		return false;
	}


	// ***** ROWS permissions *****

	/**
	 * @param int $tableId
	 * @param string|null $userId
	 * @return bool
	 */
	public function canReadRowsByTableId(int $tableId, ?string $userId = null): bool {
		try {
			$userId = $this->preCheckUserId($userId);
		} catch (InternalError $e) {
			return false;
		}

		if ($userId === '') {
			return true;
		}

		try {
			$table = $this->tableMapper->find($tableId);

			/** @var string $userId */
			if ($this->userIsTableOwner($userId, $table)) {
				return true;
			}

			try {
				$share = $this->getShareForTable($table, $userId);
				/** @noinspection PhpUndefinedMethodInspection */
				return !!$share->getPermissionRead() || !!$share->getPermissionManage();
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
	public function canCreateRowsByTableId(int $tableId, ?string $userId = null): bool {
		try {
			$userId = $this->preCheckUserId($userId);
		} catch (InternalError $e) {
			return false;
		}

		if ($userId === '') {
			return true;
		}

		try {
			$table = $this->tableMapper->find($tableId);

			/** @var string $userId */
			if ($this->userIsTableOwner($userId, $table)) {
				return true;
			}

			try {
				$share = $this->getShareForTable($table, $userId);
				/** @noinspection PhpUndefinedMethodInspection */
				return !!$share->getPermissionCreate() || !!$share->getPermissionManage();
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
	public function canUpdateRowsByTableId(int $tableId, ?string $userId = null): bool {
		try {
			$userId = $this->preCheckUserId($userId);
		} catch (InternalError $e) {
			return false;
		}

		if ($userId === '') {
			return true;
		}

		try {
			$table = $this->tableMapper->find($tableId);

			/** @var string $userId */
			if ($this->userIsTableOwner($userId, $table)) {
				return true;
			}

			try {
				$share = $this->getShareForTable($table, $userId);
				/** @noinspection PhpUndefinedMethodInspection */
				return !!$share->getPermissionUpdate() || !!$share->getPermissionManage();
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
	public function canDeleteRowsByTableId(int $tableId, ?string $userId = null): bool {
		try {
			$userId = $this->preCheckUserId($userId);
		} catch (InternalError $e) {
			return false;
		}

		if ($userId === '') {
			return true;
		}

		try {
			$table = $this->tableMapper->find($tableId);

			/** @var string $userId */
			if ($this->userIsTableOwner($userId, $table)) {
				return true;
			}

			try {
				$share = $this->getShareForTable($table, $userId);
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
				/** @var string $userId */
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
	 * @param Table $table
	 * @param string $userId
	 * @return Share
	 * @throws InternalError
	 * @throws NotFoundError
	 */
	private function getShareForTable(Table $table, string $userId): Share {
		// if shared by user
		try {
			return $this->shareMapper->findShareForNode($table->getId(), 'table', $userId, 'user');
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
				return $this->shareMapper->findShareForNode($table->getId(), 'table', $userGroup->getGID(), 'group');
			} catch (Exception $e) {
			}
		}
		throw new NotFoundError('no share found');
	}

	/** @noinspection PhpUndefinedMethodInspection */
	private function userIsTableOwner(string $userId, Table $table): bool {
		return $table->getOwnership() === $userId;
	}
}
