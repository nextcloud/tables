<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Model;

use InvalidArgumentException;
use OCA\Tables\Model\SortRuleSet;
use PHPUnit\Framework\TestCase;

class SortRuleSetTest extends TestCase {

	public function testCreateFromInputArrayWithValidData(): void {
		$data = [
			['columnId' => 1, 'mode' => 'ASC'],
			['columnId' => 2, 'mode' => 'DESC'],
		];
		$ruleSet = SortRuleSet::createFromInputArray($data);
		$serialized = $ruleSet->jsonSerialize();

		$this->assertCount(2, $serialized);
		$this->assertSame(1, $serialized[0]['columnId']);
		$this->assertSame('ASC', $serialized[0]['mode']);
		$this->assertSame(2, $serialized[1]['columnId']);
		$this->assertSame('DESC', $serialized[1]['mode']);
	}

	public function testCreateFromInputArrayWithEmptyArray(): void {
		$ruleSet = SortRuleSet::createFromInputArray([]);
		$this->assertSame([], $ruleSet->jsonSerialize());
	}

	public function testCreateFromInputArrayThrowsWhenEntryIsNotAnArray(): void {
		$this->expectException(InvalidArgumentException::class);
		SortRuleSet::createFromInputArray(['not-an-array']);
	}

	public function testCreateFromInputArrayThrowsWhenEntryIsAnInteger(): void {
		$this->expectException(InvalidArgumentException::class);
		SortRuleSet::createFromInputArray([42]);
	}

	public function testCreateFromInputArrayThrowsWhenColumnIdIsMissing(): void {
		$this->expectException(InvalidArgumentException::class);
		SortRuleSet::createFromInputArray([['mode' => 'ASC']]);
	}

	public function testCreateFromInputArrayThrowsWhenModeIsMissing(): void {
		$this->expectException(InvalidArgumentException::class);
		SortRuleSet::createFromInputArray([['columnId' => 1]]);
	}

	public function testCreateFromInputArrayThrowsWhenModeIsInvalid(): void {
		$this->expectException(InvalidArgumentException::class);
		SortRuleSet::createFromInputArray([['columnId' => 1, 'mode' => 'INVALID']]);
	}
}
