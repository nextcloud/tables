<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Db\Column;
use OCA\Tables\Service\JsonSchemaExportService;
use PHPUnit\Framework\TestCase;

class JsonSchemaExportServiceTest extends TestCase {
	private JsonSchemaExportService $service;

	public function setUp(): void {
		$this->service = new JsonSchemaExportService();
	}

	private function column(string $type, string $subtype, string $title, string $technicalName = ''): Column {
		$column = new Column();
		$column->setType($type);
		$column->setSubtype($subtype);
		$column->setTitle($title);
		$column->setTechnicalName($technicalName);
		return $column;
	}

	/**
	 * @param Column[] $columns
	 * @return array<string, mixed>
	 */
	private function properties(array $columns): array {
		$schema = $this->service->export('Projects', '', $columns);
		return (array)$schema['properties'];
	}

	public function testTheDocumentDescribesAnObjectInTheDraft202012Dialect(): void {
		$schema = $this->service->export('Projects', 'Running projects', []);

		$this->assertSame('https://json-schema.org/draft/2020-12/schema', $schema['$schema']);
		$this->assertSame('Projects', $schema['title']);
		$this->assertSame('Running projects', $schema['description']);
		$this->assertSame('object', $schema['type']);
		$this->assertArrayNotHasKey('required', $schema);
		$this->assertSame('{}', json_encode($schema['properties']));
	}

	public function testPropertiesAreKeyedByTechnicalNameAndFallBackToTheTitle(): void {
		$properties = $this->properties([
			$this->column('text', 'line', 'Project name', 'name'),
			$this->column('text', 'line', 'Notes'),
		]);

		$this->assertSame(['name', 'Notes'], array_keys($properties));
		$this->assertSame('Project name', $properties['name']['title']);
	}

	public function testMandatoryColumnsAreRequired(): void {
		$mandatory = $this->column('text', 'line', 'Name', 'name');
		$mandatory->setMandatory(true);

		$schema = $this->service->export('Projects', '', [$mandatory, $this->column('text', 'line', 'Notes', 'notes')]);

		$this->assertSame(['name'], $schema['required']);
	}

	public function testATextColumnCarriesItsLengthAndPattern(): void {
		$column = $this->column('text', 'line', 'Code', 'code');
		$column->setTextMaxLength(10);
		$column->setTextAllowedPattern('^[A-Z]+$');
		$column->setDescription('Project code');

		$this->assertSame(
			['type' => 'string', 'maxLength' => 10, 'pattern' => '^[A-Z]+$', 'title' => 'Code', 'description' => 'Project code'],
			$this->properties([$column])['code'],
		);
	}

	public function testAnUnlimitedTextColumnHasNoMaxLength(): void {
		$column = $this->column('text', 'line', 'Notes', 'notes');
		$column->setTextMaxLength(-1);

		$this->assertArrayNotHasKey('maxLength', $this->properties([$column])['notes']);
	}

	public function testANumberColumnCarriesItsBoundsAndIsAnIntegerWithoutDecimals(): void {
		$budget = $this->column('number', '', 'Budget', 'budget');
		$budget->setNumberMin(0.0);
		$budget->setNumberMax(1000.5);
		$budget->setNumberDecimals(2);
		$count = $this->column('number', '', 'Count', 'count');
		$count->setNumberDecimals(0);

		$properties = $this->properties([$budget, $count]);

		$this->assertSame(['type' => 'number', 'minimum' => 0.0, 'maximum' => 1000.5, 'title' => 'Budget'], $properties['budget']);
		$this->assertSame('integer', $properties['count']['type']);
	}

	public function testSelectionColumnsBecomeEnumsArraysOrBooleans(): void {
		$options = json_encode([['id' => 1, 'label' => 'open'], ['id' => 2, 'label' => 'done']]);
		$single = $this->column('selection', '', 'Status', 'status');
		$single->setSelectionOptions($options);
		$multi = $this->column('selection', 'selection-multi', 'Tags', 'tags');
		$multi->setSelectionOptions($options);

		$properties = $this->properties([$single, $multi, $this->column('selection', 'check', 'Done', 'done')]);

		$this->assertSame(['open', 'done'], $properties['status']['enum']);
		$this->assertSame(['type' => 'array', 'items' => ['enum' => ['open', 'done']], 'uniqueItems' => true, 'title' => 'Tags'], $properties['tags']);
		$this->assertSame('boolean', $properties['done']['type']);
	}

	public function testDatetimeColumnsUseTheMatchingFormat(): void {
		$properties = $this->properties([
			$this->column('datetime', 'date', 'Due', 'due'),
			$this->column('datetime', 'time', 'Start', 'start'),
			$this->column('datetime', '', 'Updated', 'updated'),
		]);

		$this->assertSame('date', $properties['due']['format']);
		$this->assertSame('time', $properties['start']['format']);
		$this->assertSame('date-time', $properties['updated']['format']);
	}

	public function testColumnTypesWithoutAMappingAcceptAnyValue(): void {
		$properties = $this->properties([
			$this->column('usergroup', 'user', 'Owner', 'owner'),
			$this->column('relation', '', 'Client', 'client'),
		]);

		$this->assertSame(['title' => 'Owner'], $properties['owner']);
		$this->assertSame(['title' => 'Client'], $properties['client']);
	}
}
