<?php

namespace OCA\Tables\Service;

use Exception;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;

class TableService {

	/** @var TableMapper */
	private $mapper;

	public function __construct(TableMapper $mapper) {
		$this->mapper = $mapper;
	}

    /**
     * @throws \OCP\DB\Exception
     */
    public function findAll(string $userId): array {
		return $this->mapper->findAll($userId);
	}

    /**
     * @throws TableNotFound
     * @throws Exception
     */
    private function handleException(Exception $e): void {
		if ($e instanceof DoesNotExistException ||
			$e instanceof MultipleObjectsReturnedException) {
			throw new TableNotFound($e->getMessage());
		} else {
			throw $e;
		}
	}

	public function find($id, $userId) {
		try {
			return $this->mapper->find($id, $userId);

			// in order to be able to plug in different storage backends like files
		// for instance it is a good idea to turn storage related exceptions
		// into service related exceptions so controllers and service users
		// have to deal with only one type of exception
		} catch (Exception $e) {
			$this->handleException($e);
		}
    }

    /**
     * @throws \OCP\DB\Exception
     * @noinspection PhpUndefinedMethodInspection
     */
    public function create($title, $userId) {
		$item = new Table();
        $item->setTitle($title);
        $item->setOwnership($userId);
        $item->setCreatedBy($userId);
        $item->setLastEditBy($userId);
        $item->setCreatedAt('2021-09-18 01:23:45');
        $item->setLastEditAt('2021-09-18 01:23:45');
		return $this->mapper->insert($item);
	}

	public function update($id, $title, $content, $userId) {
		try {
			$note = $this->mapper->find($id, $userId);
			$note->setTitle($title);
			$note->setContent($content);
			return $this->mapper->update($note);
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}

	public function delete($id, $userId) {
		try {
			$note = $this->mapper->find($id, $userId);
			$this->mapper->delete($note);
			return $note;
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}
}
