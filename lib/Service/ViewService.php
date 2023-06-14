<?php

namespace OCA\Tables\Service;

use DateTime;
use Exception;

use OCA\Tables\Db\Table;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;


use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class ViewService extends SuperService {
	private ViewMapper $mapper;
	private TableService $tableService;

	protected IL10N $l;

	public function __construct(
		PermissionsService $permissionsService,
		LoggerInterface $logger,
		?string $userId,
		ViewMapper $mapper,
		TableService $tableService,
		IL10N $l
	) {
		parent::__construct($logger, $userId, $permissionsService);
		$this->l = $l;
		$this->mapper = $mapper;
		$this->tableService = $tableService;

	}

	/**
	 * Find all tables for a user
	 *
	 * takes the user from actual context or the given user
	 * it is possible to get all tables, but only if requested by cli
	 *
	 * @param int|null $tableId
	 * @param string|null $userId (null -> take from session, '' -> no user in context)
	 * @return array<View>
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function findAll(?int $tableId, ?string $userId = null): array {
		/** @var string $userId */
		$userId = $this->permissionsService->preCheckUserId($userId); // $userId can be set or ''

		$table = $this->getTable($tableId);

		try {
			// security
			if (!$this->permissionsService->canReadViews($table, $userId)) {
				throw new PermissionError('PermissionError: can not read views for tableId '.$tableId);
			}

			return $this->mapper->findAll($tableId);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError($e->getMessage());
		} catch (PermissionError $e) {
			$this->logger->debug('permission error during looking for views', ['exception' => $e]);
			throw new PermissionError($e->getMessage());
		}
	}


	/**
	 * @param int $id
	 * @param string|null $userId (null -> take from session, '' -> no user in context)
	 * @return View
	 * @throws PermissionError
	 * @throws NotFoundError
	 * @throws InternalError
	 */
	public function find(int $id, ?string $userId = null): View {
		/** @var string $userId */
		$userId = $this->permissionsService->preCheckUserId($userId); // $userId can be set or ''

		try {
			$view = $this->mapper->find($id);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}

		$table = $this->getTable($view->getTableId());

		// security
		if (!$this->permissionsService->canReadViews($table, $userId)) {
			throw new PermissionError('PermissionError: can not read view with id '.$id);
		}

		return $view;
	}

	/**
	 * @param $tableId
	 * @return Table
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	private function getTable($tableId): Table {
		try {
			return $this->tableService->find($tableId);
		} catch (InternalError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError($e->getMessage());
		} catch (NotFoundError $e) {
			$this->logger->warning($e->getMessage(), ['exception' => $e]);
			throw new NotFoundError($e->getMessage());
		} catch (PermissionError $e) {
			$this->logger->warning($e->getMessage(), ['exception' => $e]);
			throw new PermissionError($e->getMessage());
		}
	}


	/**
	 * @param int $tableId
	 * @param string $title
	 * @param string|null $emoji
	 * @param string|null $userId
	 * @return View
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function create(int $tableId, string $title, ?string $emoji, ?string $userId = null): View {
		/** @var string $userId */
		$userId = $this->permissionsService->preCheckUserId($userId, false); // $userId is set

		$table = $this->getTable($tableId);

		// security
		if (!$this->permissionsService->canUpdateTable($table, $userId)) {
			throw new PermissionError('PermissionError: can not create view');
		}

		$time = new DateTime();
		$item = new View();
		$item->setTitle($title);
		if($emoji) {
			$item->setEmoji($emoji);
		}
		$item->setCreatedBy($userId);
		$item->setLastEditBy($userId);
		$item->setCreatedAt($time->format('Y-m-d H:i:s'));
		$item->setLastEditAt($time->format('Y-m-d H:i:s'));
		try {
			$newItem = $this->mapper->insert($item);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}
		return $newItem;
	}


	/**
	 * @param int $id
	 * @param string $key
	 * @param string|null $value
	 * @param string|null $userId
	 * @return View
	 * @throws InternalError
	 */
	public function updateSingle(int $id, string $key, ?string $value, ?string $userId = null): View {
		return $this->update($id, [$key => $value], $userId);
	}

	/**
	 * @param int $id
	 * @param array $data
	 * @param string|null $userId
	 * @return View
	 * @throws InternalError
	 */
	public function update(int $id, array $data, ?string $userId = null): View {
		$userId = $this->permissionsService->preCheckUserId($userId);

		try {
			$view = $this->mapper->find($id);

			$table = $this->getTable($view->getTableId());

			// security
			if (!$this->permissionsService->canUpdateTable($table, $userId)) {
				throw new PermissionError('PermissionError: can not update view with id '.$id);
			}

			foreach ($data as $key => $value) {
				$setterMethod = 'set'.ucfirst($key);
				$view->$setterMethod($value);
			}
			$time = new DateTime();
			$table->setLastEditBy($userId);
			$table->setLastEditAt($time->format('Y-m-d H:i:s'));

			return $this->mapper->update($view);
		} catch (Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError($e->getMessage());
		}
	}

	/**
	 * @param int $id
	 * @param string|null $userId
	 * @return View
	 * @throws InternalError
	 */
	public function delete(int $id, ?string $userId = null): View {
		/** @var string $userId */
		$userId = $this->permissionsService->preCheckUserId($userId); // $userId is set or ''

		try {
			$view = $this->mapper->find($id);
			$table = $this->getTable($view->getTableId());

			// security
			if (!$this->permissionsService->canUpdateTable($table, $userId)) {
				throw new PermissionError('PermissionError: can not delete view with id '.$id);
			}

			$this->mapper->delete($view);
			return $view;
		} catch (Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}
	}
}
