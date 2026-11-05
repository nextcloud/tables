<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\RowSleeve;
use PHPUnit\Framework\TestCase;

class RowSleeveTest extends TestCase {
	private const UUID = '019f90c5-8576-7f0b-9b14-9bf49b2826cf';

	public function testHydrationPreservesNullAndResetsChanges(): void {
		$sleeve = RowSleeve::fromRow(['id' => 17, 'uuid' => null]);
		$this->assertNull($sleeve->getUuid());
		$this->assertNull($sleeve->jsonSerialize()['uuid']);
		$this->assertSame([], $sleeve->getUpdatedFields());
	}

	public function testHydrationPreservesAssignedUuid(): void {
		$sleeve = RowSleeve::fromRow(['id' => 17, 'uuid' => self::UUID]);
		$this->assertSame(self::UUID, $sleeve->getUuid());
		$this->assertSame([], $sleeve->getUpdatedFields());
	}

	public function testHydrationDoesNotRejectStoredValues(): void {
		$sleeve = RowSleeve::fromRow(['id' => 17, 'uuid' => 'not-a-uuid']);
		$this->assertSame('not-a-uuid', $sleeve->getUuid());
		$this->assertSame([], $sleeve->getUpdatedFields());
	}

	public function testAssignmentIsExplicitAndIdempotent(): void {
		$sleeve = new RowSleeve();
		$this->assertNull($sleeve->getUuid());
		$sleeve->assignUuid();
		$uuid = $sleeve->getUuid();
		$this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $uuid);
		$this->assertArrayHasKey('uuid', $sleeve->getUpdatedFields());
		$sleeve->assignUuid();
		$this->assertSame($uuid, $sleeve->jsonSerialize()['uuid']);
	}

	public function testArchiveUuidIsNormalized(): void {
		$sleeve = new RowSleeve();
		$sleeve->setUuid(strtoupper(self::UUID));
		$this->assertSame(self::UUID, $sleeve->getUuid());
	}

	/** @dataProvider immutableChanges */
	public function testAssignedIdentityCannotChange(?string $uuid): void {
		$sleeve = RowSleeve::fromRow(['uuid' => self::UUID]);
		$this->expectException(\InvalidArgumentException::class);
		$sleeve->setUuid($uuid);
	}

	public static function immutableChanges(): array {
		return [[null], ['019f90c5-8576-7f0b-9b14-9bf49b2826c0']];
	}

	/** @dataProvider invalidUuids */
	public function testInvalidUuidFails(string $uuid): void {
		$this->expectException(\InvalidArgumentException::class);
		(new RowSleeve())->setUuid($uuid);
	}

	public static function invalidUuids(): array {
		return [[''], ['invalid'], ['019f90c5-8576-4f0b-9b14-9bf49b2826cf'], ['019f90c5-8576-7f0b-0b14-9bf49b2826cf']];
	}
}
