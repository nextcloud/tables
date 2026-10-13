<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Model;

use InvalidArgumentException;
use OCA\Tables\Model\MenuItemInput;
use PHPUnit\Framework\TestCase;

class MenuItemInputTest extends TestCase {
	public function testCreatesEveryTargetTypeAndDropsWhatItDoesNotUse(): void {
		$items = MenuItemInput::createListFromInputArray([
			['label' => ' Intake home ', 'targetType' => 'view', 'targetId' => 6, 'technicalName' => 'home', 'url' => 'https://ignored.example'],
			['label' => 'Docs', 'targetType' => 'url', 'url' => 'https://example.org/docs', 'targetId' => 99, 'icon' => 'book'],
			['label' => 'Requests', 'targetType' => 'table', 'targetId' => 4, 'icon' => ''],
		]);

		$this->assertSame(['Intake home', null, 'view', 6, null, 'home'], $this->toArray($items[0]));
		$this->assertSame(['Docs', 'book', 'url', null, 'https://example.org/docs', null], $this->toArray($items[1]));
		$this->assertSame(['Requests', null, 'table', 4, null, null], $this->toArray($items[2]));
	}

	public function testATargetTypeOfUrlIsTheDefault(): void {
		$item = MenuItemInput::createFromInputArray(['label' => 'Docs', 'url' => 'http://example.org']);

		$this->assertSame('url', $item->getTargetType());
	}

	public static function invalidItems(): array {
		return [
			'not an object' => ['not an item', 'must be an object'],
			'no label' => [['targetType' => 'url', 'url' => 'https://example.org'], 'needs a label'],
			'blank label' => [['label' => '  ', 'targetType' => 'url', 'url' => 'https://example.org'], 'needs a label'],
			'label too long' => [['label' => str_repeat('x', 201), 'url' => 'https://example.org'], 'needs a label'],
			'unknown target type' => [['label' => 'X', 'targetType' => 'kanban', 'targetId' => 1], 'Unexpected menu item target type'],
			'view without id' => [['label' => 'X', 'targetType' => 'view'], 'needs its id'],
			'id as a string' => [['label' => 'X', 'targetType' => 'table', 'targetId' => '4'], 'needs its id'],
			'id of zero' => [['label' => 'X', 'targetType' => 'table', 'targetId' => 0], 'needs its id'],
			'javascript link' => [['label' => 'X', 'url' => 'javascript:alert(1)'], 'http or https'],
			'data link' => [['label' => 'X', 'url' => 'data:text/html,hi'], 'http or https'],
			// carries a host, so only the scheme check stops it
			'javascript link with a host' => [['label' => 'X', 'url' => 'javascript://example.com/%0Aalert(1)'], 'http or https'],
			'link without host' => [['label' => 'X', 'url' => 'https://'], 'http or https'],
			'empty link' => [['label' => 'X', 'url' => ''], 'http or https'],
			'technical name with a hyphen' => [['label' => 'X', 'url' => 'https://example.org', 'technicalName' => 'my-page'], 'technical name'],
			'icon that is not a string' => [['label' => 'X', 'url' => 'https://example.org', 'icon' => 3], '"icon" must be a string'],
		];
	}

	/**
	 * @dataProvider invalidItems
	 */
	public function testRejectsInvalidInput(mixed $input, string $message): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($message);
		MenuItemInput::createFromInputArray($input);
	}

	public function testKeepsTheParentPositionForOneLevelOfNesting(): void {
		$items = MenuItemInput::createListFromInputArray([
			['label' => 'Cases', 'targetType' => 'table', 'targetId' => 4],
			['label' => 'Open', 'targetType' => 'view', 'targetId' => 6, 'parentIndex' => 0],
			['label' => 'Docs', 'url' => 'https://example.org', 'parentIndex' => null],
		]);

		$this->assertSame([null, 0, null], array_map(static fn (MenuItemInput $item) => $item->getParentIndex(), $items));
	}

	public static function invalidNesting(): array {
		return [
			'under a child' => [[null, 0, 1], 'top-level'],
			'under itself' => [[null, 1], 'top-level'],
			'under a position that does not exist' => [[null, 5], 'top-level'],
			'given as a string' => [[null, '0'], 'position of that parent'],
			'given as a negative number' => [[null, -1], 'position of that parent'],
		];
	}

	/**
	 * @dataProvider invalidNesting
	 */
	public function testRejectsInvalidNesting(array $parentIndexes, string $message): void {
		$items = array_map(
			static fn (mixed $parentIndex, int $position): array => ['label' => 'Item ' . $position, 'url' => 'https://example.org/' . $position, 'parentIndex' => $parentIndex],
			$parentIndexes,
			array_keys($parentIndexes),
		);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($message);
		MenuItemInput::createListFromInputArray($items);
	}

	public function testRejectsAMenuThatIsNotAList(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('must be a list');
		MenuItemInput::createListFromInputArray(['first' => ['label' => 'X', 'url' => 'https://example.org']]);
	}

	private function toArray(MenuItemInput $item): array {
		return [$item->getLabel(), $item->getIcon(), $item->getTargetType(), $item->getTargetId(), $item->getUrl(), $item->getTechnicalName()];
	}
}
