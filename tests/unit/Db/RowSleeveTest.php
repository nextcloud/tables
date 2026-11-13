<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\RowSleeve;
use OCA\Tables\Vendor\Symfony\Component\Uid\Uuid;
use PHPUnit\Framework\TestCase;

class RowSleeveTest extends TestCase {
	private const UUID = '0199e3a0-1f2b-7c3d-8e4f-5a6b7c8d9e0f';

	public function testLoadingARowWithoutUuidKeepsItEmpty(): void {
		$sleeve = RowSleeve::fromRow(['id' => 1, 'uuid' => null, 'table_id' => 2]);

		$this->assertNull($sleeve->getUuid());
		$this->assertSame([], $sleeve->getUpdatedFields());
	}

	public function testLoadingARowKeepsItsUuid(): void {
		$sleeve = RowSleeve::fromRow(['id' => 1, 'uuid' => self::UUID, 'table_id' => 2]);

		$this->assertSame(self::UUID, $sleeve->getUuid());
	}

	public function testAssignUuidIfMissingGeneratesAUuidV7(): void {
		$sleeve = new RowSleeve();
		$sleeve->assignUuidIfMissing();

		$uuid = $sleeve->getUuid();
		$this->assertNotNull($uuid);
		$this->assertTrue(Uuid::isValid($uuid));
		$this->assertSame('7', $uuid[14]);
		$this->assertArrayHasKey('uuid', $sleeve->getUpdatedFields());
	}

	public function testAssignUuidIfMissingKeepsAnExistingUuid(): void {
		$sleeve = new RowSleeve();
		$sleeve->setUuid(self::UUID);
		$sleeve->assignUuidIfMissing();

		$this->assertSame(self::UUID, $sleeve->getUuid());
	}

	public function testUuidCannotBeChanged(): void {
		$sleeve = new RowSleeve();
		$sleeve->setUuid(self::UUID);

		$this->expectException(\RuntimeException::class);
		$sleeve->setUuid('0199e3a0-1f2b-7c3d-8e4f-000000000000');
	}

	public function testUuidCannotBeCleared(): void {
		$sleeve = new RowSleeve();
		$sleeve->setUuid(self::UUID);

		$this->expectException(\RuntimeException::class);
		$sleeve->setUuid(null);
	}

	public function testSettingTheSameUuidAgainIsAllowed(): void {
		$sleeve = new RowSleeve();
		$sleeve->setUuid(self::UUID);
		$sleeve->setUuid(self::UUID);

		$this->assertSame(self::UUID, $sleeve->getUuid());
	}

	public function testJsonSerializeContainsTheUuid(): void {
		$sleeve = new RowSleeve();
		$sleeve->setUuid(self::UUID);

		$this->assertSame(self::UUID, $sleeve->jsonSerialize()['uuid']);
	}
}
