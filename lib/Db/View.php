<?php

namespace OCA\Tables\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class View extends Entity implements JsonSerializable {
	protected ?string $title = null;
	protected ?int $tableId = null;
	protected ?string $createdBy = null;
	protected ?string $createdAt = null;
	protected ?string $lastEditBy = null;
	protected ?string $lastEditAt = null;
	protected ?string $emoji = null;
	protected ?string $description = null;
	protected ?string $columns = null; // json
	protected ?string $sort = null; // json
	protected ?string $filter = null; // json

	protected ?bool $isBaseView = false;
	protected ?bool $isShared = null;
	protected ?array $onSharePermissions = null;
	protected ?bool $hasShares = false;
	protected ?int $rowsCount = 0;
	protected ?string $ownership = null;
	protected ?string $ownerDisplayName = null;


	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('tableId', 'integer');
	}

	/** @noinspection PhpUndefinedMethodInspection */
	public function getColumnsArray():array {
		return $this->getArray($this->getColumns());
	}

	/** @noinspection PhpUndefinedMethodInspection */
	public function getSortArray():array {
		return $this->getArray($this->getSort());
	}

	/** @noinspection PhpUndefinedMethodInspection */
	public function getFilterArray():array {
		return $this->getArray($this->getFilter());
	}

	private function getArray(?string $json) {
		if ($json !== "" && $json !== null && $json !== 'null') {
			return \json_decode($json, true);
		} else {
			return [];
		}
	}

	/** @noinspection PhpUndefinedMethodInspection */
	public function setColumnsArray(array $array):void {
		$this->setColumns(\json_encode($array));
	}

	/** @noinspection PhpUndefinedMethodInspection */
	public function setSortArray(array $array):void {
		$this->setSort(\json_encode($array));
	}

	/** @noinspection PhpUndefinedMethodInspection */
	public function setFilterArray(array $array):void {
		$this->setFilter(\json_encode($array));
	}

	public function jsonSerialize(): array {
		$serialisedJson = [
			'id' => $this->id,
			'tableId' => $this->tableId,
			'title' => $this->title,
			'description' => $this->description,
			'emoji' => $this->emoji,
			'ownership' => $this->ownership,
			'createdBy' => $this->createdBy,
			'createdAt' => $this->createdAt,
			'lastEditBy' => $this->lastEditBy,
			'lastEditAt' => $this->lastEditAt,
			'columns' => $this->getColumnsArray(),
			'sort' => $this->getSortArray(),
			'isBaseView'=> $this->isBaseView,
			'isShared' => !!$this->isShared,
			'onSharePermissions' => $this->onSharePermissions,
			'hasShares' => $this->hasShares,
			'rowsCount' => $this->rowsCount,
			'ownerDisplayName' => $this->ownerDisplayName,
		];
		if (!$this->isBaseView) {
			$serialisedJson['filter'] = $this->getFilterArray();
		}

		return $serialisedJson;
	}
}
