<?php

namespace OCA\Tables\Model\Public;

use JsonSerializable;

/**
 * @since 0.8
 */
final class Row implements JsonSerializable {

	/**
	 * @param int $tableId
	 * @param int $rowId
	 * @param array<int,mixed>|null $previousValues key is the columnId. Only set on Update events.
	 * @param array<int,mixed>|null $values key is the columnId. Contains values across all events, including Delete.
	 *
	 * @since 0.8
	 */
	public function __construct(
		/** @readonly */
		public int $tableId,
		/** @readonly */
		public int $rowId,
		/** @readonly */
		public null|array $previousValues = null,
		/** @readonly */
		public null|array $values = null,
	) {
	}


	/**
	 * @return array{"tableId": int, "rowId": int, "previousValues": null|array<int, mixed>, "values": null|array<int, mixed>}
	 *
	 * @since 0.8
	 */
	public function jsonSerialize(): array {
		return [
			'tableId' => $this->tableId,
			'rowId' => $this->rowId,
			'previousValues' => $this->previousValues,
			'values' => $this->values,
		];
	}
}
