<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Model;

use OCA\Tables\Model\TableScheme;
use PHPUnit\Framework\TestCase;

class TableSchemeTest extends TestCase {

	public function testJsonSerializeDefaultsColumnOrderAndSortToEmptyArrays(): void {
		$scheme = new TableScheme('Title', '📋', [], [], '', '1.0.0');
		$data = $scheme->jsonSerialize();

		$this->assertSame([], $data['columnOrder']);
		$this->assertSame([], $data['sort']);
	}

	public function testJsonSerializeIncludesColumnOrder(): void {
		$columnOrder = [['columnId' => 3, 'order' => 1, 'readonly' => false, 'mandatory' => false]];
		$scheme = new TableScheme('Title', '📋', [], [], '', '1.0.0', $columnOrder);
		$data = $scheme->jsonSerialize();

		$this->assertSame($columnOrder, $data['columnOrder']);
		$this->assertSame([], $data['sort']);
	}

	public function testJsonSerializeIncludesSort(): void {
		$sort = [['columnId' => 5, 'mode' => 'DESC']];
		$scheme = new TableScheme('Title', '📋', [], [], '', '1.0.0', [], $sort);
		$data = $scheme->jsonSerialize();

		$this->assertSame([], $data['columnOrder']);
		$this->assertSame($sort, $data['sort']);
	}

	public function testJsonSerializeIncludesBothColumnOrderAndSort(): void {
		$columnOrder = [['columnId' => 3, 'order' => 1, 'readonly' => false, 'mandatory' => false]];
		$sort = [['columnId' => 5, 'mode' => 'DESC']];
		$scheme = new TableScheme('Title', '📋', [], [], '', '1.0.0', $columnOrder, $sort);
		$data = $scheme->jsonSerialize();

		$this->assertSame($columnOrder, $data['columnOrder']);
		$this->assertSame($sort, $data['sort']);
	}
}
