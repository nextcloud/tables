<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

class CollectionManager {
	protected array $itemsById = [];
	protected array $mapByAlias = [];
	protected array $cleanUp = [];

	public function register(mixed $item, string $type, int $id, ?string $alias = null, ?callable $cleanUpFunc = null): void {
		$idMapKey = $this->makeKey($type, $id);
		$this->itemsById[$idMapKey] = $item;

		if ($alias) {
			$aliasMapKey = $this->makeKey($type, $alias);
			$this->mapByAlias[$aliasMapKey] = $idMapKey;
		}

		if ($cleanUpFunc) {
			$this->cleanUp[$idMapKey] = [
				'aliasMapKey' => $aliasMapKey ?? null,
				'func' => $cleanUpFunc,
			];
		}
	}

	public function update(mixed $item, string $type, int $id, ?callable $cleanUpFunc = null): void {
		$idMapKey = $this->makeKey($type, $id);
		$this->itemsById[$idMapKey] = $item;
		$aliasMapKey = array_search($idMapKey, $this->mapByAlias, true) ?: null;

		if ($cleanUpFunc) {
			$this->cleanUp[$idMapKey] = [
				'aliasMapKey' => $aliasMapKey ?? null,
				'func' => $cleanUpFunc
			];
		}
	}

	public function forget(string $type, int $id, ?string $alias = null): void {
		if ($alias) {
			unset($this->mapByAlias[$this->makeKey($type, $alias)]);
		}
		$idKey = $this->makeKey($type, $id);
		unset($this->cleanUp[$idKey], $this->itemsById[$idKey]);
	}

	public function cleanUp(): void {
		foreach ($this->cleanUp as $idMapKey => $cleanUpData) {
			$cleanUpData['func']();
			if ($cleanUpData['aliasMapKey']) {
				unset($this->mapByAlias['aliasMapKey']);
			}
			unset($this->itemsById[$idMapKey]);
		}
		$this->cleanUp = [];
	}

	public function getById(string $type, int $id): mixed {
		return $this->itemsById[$this->makeKey($type, $id)] ?? null;
	}

	public function getByAlias(string $type, string $alias): mixed {
		$idMapKey = $this->mapByAlias[$this->makeKey($type, $alias)];
		return $this->itemsById[$idMapKey] ?? null;
	}

	protected function makeKey(string $type, int|string $id): string {
		return $type . '//' . $id;
	}
}
