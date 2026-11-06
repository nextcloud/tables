<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use JsonSerializable;
use OCA\Tables\ResponseDefinitions;

/**
 * @psalm-import-type TablesImportStats from ResponseDefinitions
 */
final class ImportStats implements JsonSerializable {
	/**
	 * @param list<ImportProblem> $problems
	 */
	public function __construct(
		public int $foundColumnsCount,
		public int $matchingColumnsCount,
		public int $createdColumnsCount,
		public int $insertedRowsCount,
		public int $updatedRowsCount,
		public int $errorsParsingCount,
		public int $errorsCount,
		public array $problems = [],
		public ?ImportProblemsFile $problemsFile = null,
	) {
	}

	/**
	 * Copy without the problems list, for activity/notification parameters
	 * where only the counters are rendered.
	 */
	public function withoutProblems(): self {
		$clone = clone $this;
		$clone->problems = [];
		return $clone;
	}

	/**
	 * @return TablesImportStats
	 */
	public function jsonSerialize(): array {
		return [
			'foundColumnsCount' => $this->foundColumnsCount,
			'matchingColumnsCount' => $this->matchingColumnsCount,
			'createdColumnsCount' => $this->createdColumnsCount,
			'insertedRowsCount' => $this->insertedRowsCount,
			'updatedRowsCount' => $this->updatedRowsCount,
			'errorsParsingCount' => $this->errorsParsingCount,
			'errorsCount' => $this->errorsCount,
			'problems' => array_map(static fn (ImportProblem $problem): array => $problem->jsonSerialize(), $this->problems),
			'problemsFile' => $this->problemsFile?->jsonSerialize(),
		];
	}
}
