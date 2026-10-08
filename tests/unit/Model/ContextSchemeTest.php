<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Model;

use InvalidArgumentException;
use OCA\Tables\Model\ContextScheme;
use PHPUnit\Framework\TestCase;

final class ContextSchemeTest extends TestCase {
	private const TABLES = ['addTables' => [], 'modifyTables' => []];

	public function testCreateFromInputCarriesEveryPart(): void {
		$scheme = ContextScheme::createFromInputArray([
			'name' => 'Intake', 'icon' => 'inbox', 'description' => 'desc', 'technicalName' => 'intake',
			'nodes' => [['node_type' => 0, 'node_uuid' => 'u1', 'permissions' => 1]],
			'tables' => self::TABLES,
			'menuItems' => [['label' => 'Home', 'targetType' => 'view', 'targetUuid' => 'v1']],
			'gridViews' => [['uuid' => 'v1', 'title' => 'Home']],
		]);

		$this->assertSame('Intake', $scheme->getName());
		$this->assertSame('inbox', $scheme->getIcon());
		$this->assertSame('intake', $scheme->getTechnicalName());
		$this->assertCount(1, $scheme->getNodes());
		$this->assertCount(1, $scheme->getMenuItems());
		$this->assertCount(1, $scheme->getGridViews());
		$this->assertSame(self::TABLES, $scheme->getTables());
	}

	public function testCreateFromInputTreatsABlankTechnicalNameAsNone(): void {
		$scheme = ContextScheme::createFromInputArray(['name' => 'Intake', 'tables' => self::TABLES, 'technicalName' => '']);

		$this->assertNull($scheme->getTechnicalName());
		$this->assertSame([], $scheme->getMenuItems());
	}

	public function testCreateFromInputRejectsTablesWithoutBothLists(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('addTables');
		ContextScheme::createFromInputArray(['name' => 'Intake', 'tables' => ['addTables' => []]]);
	}

	public function testCreateFromInputRejectsAMissingName(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('name');
		ContextScheme::createFromInputArray(['name' => ' ', 'tables' => self::TABLES]);
	}

	public function testCreateFromInputRejectsANonListMenu(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('menuItems');
		ContextScheme::createFromInputArray(['name' => 'Intake', 'tables' => self::TABLES, 'menuItems' => 'home']);
	}
}
