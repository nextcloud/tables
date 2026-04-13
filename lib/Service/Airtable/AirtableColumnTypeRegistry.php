<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable;

use OCA\Tables\AppInfo\Application;
use Psr\Log\LoggerInterface;

/**
 * Registry of Airtable-field-type converters.
 *
 * Each converter implements AirtableColumnTypeInterface and handles one
 * Airtable field type (e.g. 'text', 'number', 'multipleAttachments').
 * Converters are registered during application bootstrap by calling
 * register() from Application::boot() after the DI container is fully
 * initialised.
 *
 * Usage inside AirtableSchemaConverter (B0.4-B0.7):
 *
 *   $converter = $this->registry->get($rawColumn['type']);
 *   if ($converter === null) {
 *       // Unknown type – generic skip-and-report
 *       $reportRows[] = [...];
 *       continue;
 *   }
 *   $columnDto = $converter->toTablesColumn($rawColumn, $reportRows);
 */
class AirtableColumnTypeRegistry {

	/** @var array<string, AirtableColumnTypeInterface> keyed by Airtable type string */
	private array $converters = [];

	public function __construct(
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Register a converter for its declared Airtable type.
	 *
	 * A second call with the same type replaces the previous converter,
	 * which allows downstream code (e.g. Phase 1/2 converters) to upgrade
	 * a lossy Phase 0 converter without modifying the original class.
	 */
	public function register(AirtableColumnTypeInterface $converter): void {
		$type = $converter->getAirtableType();

		if (isset($this->converters[$type])) {
			$this->logger->debug('AirtableColumnTypeRegistry: replacing converter for type "{type}"', [
				'app'  => Application::APP_ID,
				'type' => $type,
			]);
		}

		$this->converters[$type] = $converter;
	}

	/**
	 * Return the converter registered for $airtableType, or null when no
	 * converter has been registered for that type.
	 *
	 * Callers should treat null as "skip and add a report row" – the same
	 * behaviour as the explicit skip-and-report converters (B0.7), but for
	 * types that are entirely unknown to this version of the importer.
	 */
	public function get(string $airtableType): ?AirtableColumnTypeInterface {
		return $this->converters[$airtableType] ?? null;
	}

	/**
	 * Return true when a converter is registered for $airtableType.
	 */
	public function has(string $airtableType): bool {
		return isset($this->converters[$airtableType]);
	}

	/**
	 * Return all registered Airtable type strings.
	 *
	 * @return list<string>
	 */
	public function getRegisteredTypes(): array {
		return array_keys($this->converters);
	}
}
