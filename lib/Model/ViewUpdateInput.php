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
		protected readonly ?string $technicalName = null,
		protected readonly ?string $description = null,
		protected readonly ?Emoji $emoji = null,
		protected readonly ?ColumnSettings $columnSettings = null,
		protected readonly ?FilterSet $filterSet = null,
		protected readonly ?SortRuleSet $sortRuleSet = null,
		protected readonly ?int $sidebarOrder = null,
		protected readonly ?string $layout = null,
		protected readonly ?ViewSettings $viewSettings = null,
	) {
	}

	public function updateDetail(): Generator {
		if ($this->sidebarOrder !== null) {
			yield ViewUpdatableParameters::SIDEBAR_ORDER => $this->sidebarOrder;
		}
		if ($this->title) {
			yield ViewUpdatableParameters::TITLE => $this->title;
		}
		if ($this->technicalName !== null) {
			yield ViewUpdatableParameters::TECHNICAL_NAME => $this->technicalName;
		}
		if ($this->description !== null) {
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
		if ($this->viewSettings !== null) {
			yield ViewUpdatableParameters::VIEW_SETTINGS => $this->viewSettings;
		}
	}

	/**
	 * @param array{
	 *     title?: string,
	 *     emoji?: string,
	 *     description?: string,
	 *     technicalName?: string,
	 *     columns?: list<int>,
	 *     columnSettings?: list<array{columnId?: int, order?: int, readonly?: bool, mandatory?: bool}>,
	 *     sort?: list<array{columnId: int, mode: 'ASC'|'DESC'}>,
	 *     layout?: 'table'|'tiles'|'gallery'|null,
	 *     viewSettings?: array{cardBackgroundSource?: int|null, cardTitleSource?: int|null}|string,
	 *     filter?: list<list<array{columnId: int, operator: 'begins-with'|'ends-with'|'contains'|'does-not-contain'|'is-equal'|'is-not-equal'|'is-greater-than'|'is-greater-than-or-equal'|'is-lower-than'|'is-lower-than-or-equal'|'is-empty', value: string|int|float}>>,
	 *     sidebarOrder?: int
	 * } $data
	 * @param array $columnsMap
	 */
	public static function fromInputArray(array $data, array $columnsMap = []): self {
		$data = self::transformJsonToArrayInPayload($data, ['columnSettings', 'filter', 'sort', 'viewSettings']);

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
		$viewSettings = self::createViewSettingsFromInputData($data);

		return new self(
			title: ($data['title'] ?? null) ? new Title($data['title']) : null,
			technicalName: $data['technicalName'] ?? null,
			description: $data['description'] ?? null,
			emoji: ($data['emoji'] ?? null) ? new Emoji($data['emoji']) : null,
			columnSettings: isset($data['columnSettings']) ? ColumnSettings::createViewSettingsFromInputArray($data['columnSettings'], $columnsMap) : null,
			filterSet: isset($data['filter']) ? FilterSet::createFromInputArray($data['filter'], $columnsMap) : null,
			sortRuleSet: isset($data['sort']) ? SortRuleSet::createFromInputArray($data['sort'], $columnsMap) : null,
			sidebarOrder: (array_key_exists('sidebarOrder', $data) && $data['sidebarOrder'] !== null) ? (int)$data['sidebarOrder'] : null,
			layout: $layout,
			viewSettings: $viewSettings,
		);
	}

	private static function createViewSettingsFromInputData(array $data): ?ViewSettings {
		if (array_key_exists('viewSettings', $data)) {
			if ($data['viewSettings'] === null) {
				return new ViewSettings();
			}
			if (!is_array($data['viewSettings'])) {
				throw new \InvalidArgumentException('Invalid viewSettings value.');
			}
			return ViewSettings::createFromInputArray($data['viewSettings']);
		}

		$legacyKeys = ['cardBackgroundSource', 'cardTitleSource'];
		$hasLegacySettings = false;
		foreach ($legacyKeys as $legacyKey) {
			if (array_key_exists($legacyKey, $data)) {
				$hasLegacySettings = true;
				break;
			}
		}
		if (!$hasLegacySettings) {
			return null;
		}

		return ViewSettings::createFromInputArray([
			'cardBackgroundSource' => $data['cardBackgroundSource'] ?? null,
			'cardTitleSource' => $data['cardTitleSource'] ?? null,
		]);
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
