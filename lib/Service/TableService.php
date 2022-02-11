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

    /** @var TableTemplateService */
    private $tableTemplateService;

    /** @var ColumnService */
    private $columnService;

    /** @var RowService */
    private $rowService;

	public function __construct(TableMapper $mapper, TableTemplateService $tableTemplateService, ColumnService $columnService, RowService $rowService) {
		$this->mapper = $mapper;
        $this->tableTemplateService = $tableTemplateService;
        $this->columnService = $columnService;
        $this->rowService = $rowService;
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
    public function create($title, $userId, $template) {
        $time = new \DateTime();
		$item = new Table();
        $item->setTitle($title);
        $item->setOwnership($userId);
        $item->setCreatedBy($userId);
        $item->setLastEditBy($userId);
        $item->setCreatedAt($time->format('Y-m-d H:i:s'));
        $item->setLastEditAt($time->format('Y-m-d H:i:s'));
		$newTable = $this->mapper->insert($item);
        if($template !== 'custom') {
            return $this->tableTemplateService->makeTemplate($newTable, $template);
        }
        return $newTable;
	}

    /** @noinspection PhpUndefinedMethodInspection */
    public function update($id, $title, $userId) {
		try {
            $time = new \DateTime();
            $item = $this->mapper->find($id, $userId);
            $item->setTitle($title);
            $item->setLastEditBy($userId);
            $item->setLastEditAt($time->format('Y-m-d H:i:s'));
			return $this->mapper->update($item);
		} catch (Exception $e) {
			$this->handleException($e);
		}
        return null;
	}

	public function delete($id, $userId) {
		try {
            $this->rowService->deleteAllByTable($id);
            $columns = $this->columnService->findAllByTable($userId, $id);
            foreach ($columns as $column) {
                $this->columnService->delete($column->id, $userId, true);
            }
            $item = $this->mapper->find($id, $userId);
			$this->mapper->delete($item);
			return $item;
		} catch (Exception $e) {
			$this->handleException($e);
        }
        return null;
    }
}
