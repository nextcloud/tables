<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Model;

use InvalidArgumentException;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Model\ContextNodeInputSet;
use PHPUnit\Framework\TestCase;

class ContextNodeInputSetTest extends TestCase {

	public function testCreateFromInputArrayKeepsValuesAndSequence(): void {
		$nodeSet = ContextNodeInputSet::createFromInputArray([
			['id' => 7, 'type' => Application::NODE_TYPE_VIEW, 'permissions' => 3, 'order' => 20],
			['id' => 5, 'type' => Application::NODE_TYPE_TABLE, 'permissions' => 1],
		]);

		$this->assertSame([
			['id' => 7, 'type' => Application::NODE_TYPE_VIEW, 'permissions' => 3, 'order' => 20],
			['id' => 5, 'type' => Application::NODE_TYPE_TABLE, 'permissions' => 1],
		], $nodeSet->jsonSerialize());
	}

	public function testCreateFromInputArrayAcceptsStringsOfDigits(): void {
		$nodeSet = ContextNodeInputSet::createFromInputArray([
			['id' => '05', 'type' => '1', 'permissions' => '0', 'order' => '30'],
		]);

		$this->assertSame([
			['id' => 5, 'type' => 1, 'permissions' => 0, 'order' => 30],
		], $nodeSet->jsonSerialize());
	}

	public function testCreateFromInputArrayLeavesOutMissingPermissions(): void {
		$nodeSet = ContextNodeInputSet::createFromInputArray([
			['id' => 5, 'type' => 0],
			['id' => 6, 'type' => 0, 'permissions' => null],
		]);

		$this->assertSame([
			['id' => 5, 'type' => 0],
			['id' => 6, 'type' => 0],
		], $nodeSet->jsonSerialize());
	}

	public function testCreateFromInputArrayLeavesOutAnOrderOfNull(): void {
		$nodeSet = ContextNodeInputSet::createFromInputArray([
			['id' => 5, 'type' => 0, 'permissions' => 1, 'order' => null],
		]);

		$this->assertSame([
			['id' => 5, 'type' => 0, 'permissions' => 1],
		], $nodeSet->jsonSerialize());
	}

	public function testCreateFromInputArrayWithEmptyArray(): void {
		$nodeSet = ContextNodeInputSet::createFromInputArray([]);
		$this->assertSame([], $nodeSet->jsonSerialize());
	}

	public function testCreateFromInputArrayAcceptsTheLargestStorableInteger(): void {
		$nodeSet = ContextNodeInputSet::createFromInputArray([
			['id' => 2147483647, 'type' => 0, 'permissions' => '2147483647'],
		]);

		$this->assertSame([
			['id' => 2147483647, 'type' => 0, 'permissions' => 2147483647],
		], $nodeSet->jsonSerialize());
	}

	/**
	 * @dataProvider invalidListProvider
	 */
	public function testCreateFromInputArrayThrowsForAnInvalidList(array $data, string $expectedMessage): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($expectedMessage);
		ContextNodeInputSet::createFromInputArray($data);
	}

	public static function invalidListProvider(): array {
		$node = ['id' => 5, 'type' => 0, 'permissions' => 1];

		return [
			'string keys' => [['a' => $node], 'Nodes must be given as a list'],
			'keys out of sequence' => [[5 => $node, 2 => $node], 'Nodes must be given as a list'],
			'keys not starting at zero' => [[1 => $node], 'Nodes must be given as a list'],
			'integer entry' => [[42], 'Each node must be an object'],
			'string entry' => [['not-an-array'], 'Each node must be an object'],
			'null entry' => [[null], 'Each node must be an object'],
			'invalid entry after a valid one' => [[$node, ['id' => 6]], 'Unexpected node type'],
		];
	}

	/**
	 * @dataProvider invalidNodeProvider
	 */
	public function testCreateFromInputArrayThrowsForAnInvalidNode(array $node, string $expectedMessage): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($expectedMessage);
		ContextNodeInputSet::createFromInputArray([$node]);
	}

	public static function invalidNodeProvider(): array {
		return [
			'missing id' => [['type' => 0], 'Unexpected node id'],
			'word as id' => [['id' => 'abc', 'type' => 0], 'Unexpected node id'],
			'decimal string as id' => [['id' => '5.7', 'type' => 0], 'Unexpected node id'],
			'empty string as id' => [['id' => '', 'type' => 0], 'Unexpected node id'],
			'padded string as id' => [['id' => ' 5', 'type' => 0], 'Unexpected node id'],
			'signed string as id' => [['id' => '+5', 'type' => 0], 'Unexpected node id'],
			'negative id' => [['id' => -1, 'type' => 0], 'Unexpected node id'],
			'float as id' => [['id' => 1.0, 'type' => 0], 'Unexpected node id'],
			'boolean as id' => [['id' => true, 'type' => 0], 'Unexpected node id'],
			'array as id' => [['id' => [5], 'type' => 0], 'Unexpected node id'],
			'id beyond the column range' => [['id' => 2147483648, 'type' => 0], 'Unexpected node id'],
			'string id beyond the column range' => [['id' => '2147483648', 'type' => 0], 'Unexpected node id'],
			'string id beyond the integer range' => [['id' => '9223372036854775808', 'type' => 0], 'Unexpected node id'],
			'missing type' => [['id' => 5], 'Unexpected node type'],
			'word as type' => [['id' => 5, 'type' => 'table'], 'Unexpected node type'],
			'unknown type' => [['id' => 5, 'type' => 7], 'Unexpected node type'],
			'word as permissions' => [['id' => 5, 'type' => 0, 'permissions' => 'abc'], 'Unexpected node permissions'],
			'empty string as permissions' => [['id' => 5, 'type' => 0, 'permissions' => ''], 'Unexpected node permissions'],
			'boolean as permissions' => [['id' => 5, 'type' => 0, 'permissions' => true], 'Unexpected node permissions'],
			'array as permissions' => [['id' => 5, 'type' => 0, 'permissions' => [1]], 'Unexpected node permissions'],
			'word as order' => [['id' => 5, 'type' => 0, 'order' => 'first'], 'Unexpected node order'],
			'negative order' => [['id' => 5, 'type' => 0, 'order' => -10], 'Unexpected node order'],
		];
	}
}
