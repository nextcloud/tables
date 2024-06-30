<?php

namespace OCA\Tables\Controller;

use JsonSerializable;
use OCA\Tables\Db\Column;

class TableScheme implements JsonSerializable {

	protected ?string $title = null;
	protected ?string $emoji = null;

	/** @var Column[]|null  */
	protected ?array $columns = null;
	protected ?string $description = null;

	public function __construct(string $title, string $emoji, array $columns, string $description)
	{
		$this->title = $title;
		$this->emoji = $emoji;
		$this->columns = $columns;
		$this->description = $description;
	}

	public function getTitle():string {
		return $this->title;
	}

	public function jsonSerialize(): array
	{
		return [
			'title' => $this->title ?: '',
			'emoji' => $this->emoji,
			'columns' => $this->columns,
			'description' => $this->description ?:'',
		];
	}

	public function getEmoji(): ?string
	{
		return $this->emoji;
	}

	public function getColumns(): ?array
	{
		return $this->columns;
	}

	public function getDescription(): ?string
	{
		return $this->description;
	}
}
