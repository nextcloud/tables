<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use Iterator;
use JsonSerializable;

/**
 * @template-implements Iterator<SelectionOption>
 */
class SelectionOptions implements JsonSerializable, Iterator {

	public function __construct(
		/** @var SelectionOption[]|null */
		private ?array $selectionOptions,
		private mixed $default,
	) {
		if ($this->selectionOptions !== null) {
			// `check` subtype has options set to null
			foreach ($this->selectionOptions as $selectionOption) {
				if (!$selectionOption instanceof SelectionOption) {
					throw new \InvalidArgumentException('Provided selectionOption must be an instance of SelectionOption');
				}
			}
		}
		if (is_int($this->default)) {
			$this->applyIntDefault();
		} elseif (is_string($this->default)) {
			$this->applyStringDefault();
		}
	}

	private function applyIntDefault(): void {
		// default value targets a specific key
		foreach ($this->selectionOptions as $selectionOption) {
			if ($selectionOption->key() === $this->default) {
				return;
			}
		}
		// if the default is not available anymore, we pragmatically unset it.
		$this->default = null;
	}

	private function applyStringDefault(): void {
		// default value is a JSON string targeting multiple keys

		$workDefault = \json_decode($this->default(), true);
		if (!is_array($workDefault)) {
			$this->default = null;
			return;
		}

		$confirmedOptions = [];
		foreach ($workDefault as $defaultOption) {
			$normalizedDefaultOption = (int)$defaultOption;
			foreach ($this->selectionOptions as $selectionOption) {
				if ($selectionOption->key() === $normalizedDefaultOption) {
					$confirmedOptions[] = $normalizedDefaultOption;
					continue 2;
				}
				// if the default is not available anymore, we pragmatically ignore it.
			}
		}
		$this->default = $confirmedOptions;
	}

	public static function createFromInputArray(?array $data, null|bool|int|string $default): self {
		if ($data !== null) {
			$selectionOptions = [];
			foreach ($data as $inputSelectionOption) {
				$selectionOptions[] = SelectionOption::createFromInputArray($inputSelectionOption);
			}
		}
		// `check` subtype has null as options
		return new self($selectionOptions ?? null, $default);
	}

	public static function createFromInputJsonString(?string $data, null|bool|int|string $default): self {
		if ($data !== null && $data !== 'null') {
			$inputArray = \json_decode($data === '' ? '[]' : $data, true);
			if (!is_array($inputArray)) {
				throw new \InvalidArgumentException('Provided selectionOption is not a valid JSON string');
			}
		} else {
			// `check` subtype has "null" as options
			$inputArray = null;
		}
		return self::createFromInputArray($inputArray, $default);
	}

	public function default(): mixed {
		return $this->default;
	}

	public function defaultSerialized(): string {
		return \json_encode($this->default());
	}

	#[\Override]
	public function jsonSerialize(): ?array {
		if ($this->selectionOptions === null) {
			return null;
		}
		return array_map(static fn (SelectionOption $so) => $so->jsonSerialize(), $this->selectionOptions);
	}

	#[\Override]
	public function current(): SelectionOption {
		return current($this->selectionOptions);
	}

	#[\Override]
	public function next(): void {
		next($this->selectionOptions);
	}

	#[\Override]
	public function key(): ?int {
		return key($this->selectionOptions);
	}

	#[\Override]
	public function valid(): bool {
		return $this->key() !== null;
	}

	#[\Override]
	public function rewind(): void {
		reset($this->selectionOptions);
	}
}
