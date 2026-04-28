<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use Generator;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Constants\ViewUpdatableParameters;
use OCA\Tables\Service\ValueObject\Emoji;
use OCA\Tables\Service\ValueObject\Title;
use OCA\Tables\Service\ValueObject\ViewColumnInformation;
use OCP\Server;
use Psr\Log\LoggerInterface;
use function json_encode;

class ViewUpdateInput {
	protected ?array $sort = null;

	public function __construct(
		protected readonly ?Title $title = null,
		protected readonly ?string $description = null,
		protected readonly ?Emoji $emoji = null,
		protected readonly ?ColumnSettings $columnSettings = null,
		protected readonly ?FilterSet $filterSet = null,
		protected readonly ?SortRuleSet $sortRuleSet = null,
		protected readonly ?string $layout = null,
		protected readonly ?int $cardBackgroundSource = null,
		protected readonly ?int $cardTitleSource = null,
	) {
	}

	public function updateDetail(): Generator {
		if ($this->title) {
			yield ViewUpdatableParameters::TITLE => $this->title;
		}
		if ($this->description) {
			yield ViewUpdatableParameters::DESCRIPTION => $this->description;
		}
		if ($this->emoji) {
			yield ViewUpdatableParameters::EMOJI => $this->emoji;
		}
		if ($this->columnSettings) {
			yield ViewUpdatableParameters::COLUMN_SETTINGS => $this->columnSettings;
		}
		if ($this->filterSet) {
			yield ViewUpdatableParameters::FILTER => $this->filterSet;
		}
		if ($this->sortRuleSet) {
			yield ViewUpdatableParameters::SORT => $this->sortRuleSet;
		}
		if ($this->layout !== null) {
			yield ViewUpdatableParameters::LAYOUT => $this->layout;
		}
		if ($this->cardBackgroundSource !== null) {
			yield ViewUpdatableParameters::CARD_BACKGROUND_SOURCE => $this->cardBackgroundSource;
		}
		if ($this->cardTitleSource !== null) {
			yield ViewUpdatableParameters::CARD_TITLE_SOURCE => $this->cardTitleSource;
		}
	}

	/**
	 * @param array{
	 *     title?: string,
	 *     emoji?: string,
	 *     description?: string,
	 *     columns?: list<int>,
	 *     columnSettings?: list<array{columnId?: int, order?: int, readonly?: bool, mandatory?: bool}>,
	 *     sort?: list<array{columnId: int, mode: 'ASC'|'DESC'}>,
	 *     layout?: 'table'|'tiles'|'gallery'|null,
	 *     cardBackgroundSource?: int|null,
	 *     cardTitleSource?: int|null,
	 *     filter?: list<list<array{columnId: int, operator: 'begins-with'|'ends-with'|'contains'|'does-not-contain'|'is-equal'|'is-not-equal'|'is-greater-than'|'is-greater-than-or-equal'|'is-lower-than'|'is-lower-than-or-equal'|'is-empty', value: string|int|float}>>
	 * } $data
	 */
	public static function fromInputArray(array $data): self {
		$data = self::transformJsonToArrayInPayload($data, ['columnSettings', 'filter', 'sort']);

		if (isset($data['columns']) && !isset($data['columnSettings'])) {
			$logger = Server::get(LoggerInterface::class);
			$logger->info('The old columns format is deprecated. Please use the new format with columnId and order properties.', ['app' => Application::APP_ID]);

			$value = [];
			foreach ($data['columns'] as $order => $columnId) {
				$value[] = new ViewColumnInformation($columnId, order: $order);
			}
			$value = json_encode($value);

			$data['columnSettings'] = $value;
		}

		$layout = self::normalizeLayout($data['layout'] ?? null);

		return new self(
			title: ($data['title'] ?? null) ? new Title($data['title']) : null,
			description: $data['description'] ?? null,
			emoji: ($data['emoji'] ?? null) ? new Emoji($data['emoji']) : null,
			columnSettings: ($data['columnSettings'] ?? null) ? ColumnSettings::createViewSettingsFromInputArray($data['columnSettings']) : null,
			filterSet: ($data['filter'] ?? null) ? FilterSet::createFromInputArray($data['filter']) : null,
			sortRuleSet: ($data['sort'] ?? null) ? SortRuleSet::createFromInputArray($data['sort']) : null,
			layout: $layout,
			cardBackgroundSource: array_key_exists('cardBackgroundSource', $data) && $data['cardBackgroundSource'] !== null ? (int)$data['cardBackgroundSource'] : null,
			cardTitleSource: array_key_exists('cardTitleSource', $data) && $data['cardTitleSource'] !== null ? (int)$data['cardTitleSource'] : null,
		);
	}


	private static function normalizeLayout(mixed $layout): ?string {
		if ($layout === null || $layout === '') {
			return null;
		}

		if (!is_string($layout)) {
			throw new \InvalidArgumentException('Invalid layout value.');
		}

		if (!in_array($layout, ['table', 'tiles', 'gallery'], true)) {
			throw new \InvalidArgumentException('Invalid layout value.');
		}

		return $layout;
	}

	protected static function transformJsonToArrayInPayload(array $input, array $keys): array {
		$output = $input;
		foreach ($keys as $targetKey) {
			if (!isset($input[$targetKey]) || !is_string($input[$targetKey])) {
				continue;
			}
			$decoded = \json_decode($input[$targetKey], true);
			if (is_array($decoded)) {
				$output[$targetKey] = $decoded;
			}
		}
		return $output;
	}
}
