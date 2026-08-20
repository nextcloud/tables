<?php

declare(strict_types = 1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Model;

use OCA\Tables\Model\SelectionOption;
use OCA\Tables\Model\SelectionOptions;
use PHPUnit\Framework\TestCase;

class SelectionOptionUuidTest extends TestCase {

	public function testExternalInputIgnoresClientUuid(): void {
		$option = SelectionOption::createFromInputArray([
			'id' => 0,
			'label' => 'Not started',
			'uuid' => '00000000-0000-0000-0000-000000000000',
		]);

		$this->assertNull($option->uuid());
		$this->assertArrayNotHasKey('uuid', $option->jsonSerialize());
	}

	public function testInternalInputKeepsUuid(): void {
		$uuid = '019f90c5-8576-7f0b-9b14-9bf49b2826cf';
		$option = SelectionOption::createFromInputArray([
			'id' => 0,
			'label' => 'Not started',
			'uuid' => $uuid,
		], true);

		$this->assertSame($uuid, $option->uuid());
	}

	public function testUpdatePreservesStoredUuidAndIgnoresClientUuid(): void {
		$storedUuid = '019f90c5-8576-7f0b-9b14-9bf49b2826cf';
		$existing = SelectionOptions::createFromInputArray([
			['id' => 0, 'label' => 'Not started', 'uuid' => $storedUuid],
		], null, true);

		$incoming = SelectionOptions::createFromInputJsonString(
			'[{"id":0,"label":"➡️ Not started ","uuid":"00000000-0000-0000-0000-000000000000"}]',
			null,
		);
		$incoming->assignServerManagedUuids($existing);

		$serialized = $incoming->jsonSerialize();
		$this->assertSame($storedUuid, $serialized[0]['uuid']);
		$this->assertSame('➡️ Not started ', $serialized[0]['label']);
	}

	public function testLegacyOptionWithoutUuidGetsFirstTimeAssignment(): void {
		$existing = SelectionOptions::createFromInputArray([
			['id' => 0, 'label' => 'Old'],
		], null);

		$incoming = SelectionOptions::createFromInputArray([
			['id' => 0, 'label' => 'Old renamed'],
		], null);
		$incoming->assignServerManagedUuids($existing);

		$serialized = $incoming->jsonSerialize();
		$this->assertNotEmpty($serialized[0]['uuid']);
	}

	public function testNewOptionGetsUuidOnCreate(): void {
		$existing = SelectionOptions::createFromInputArray([
			['id' => 0, 'label' => 'Old', 'uuid' => '019f90c5-8576-7f0b-9b14-9bf49b2826cf'],
		], null, true);

		$incoming = SelectionOptions::createFromInputArray([
			['id' => 0, 'label' => 'Old'],
			['id' => 1, 'label' => 'New option'],
		], null);
		$incoming->assignServerManagedUuids($existing);

		$serialized = $incoming->jsonSerialize();
		$this->assertSame('019f90c5-8576-7f0b-9b14-9bf49b2826cf', $serialized[0]['uuid']);
		$this->assertNotEmpty($serialized[1]['uuid']);
		$this->assertNotSame($serialized[0]['uuid'], $serialized[1]['uuid']);
	}
}
