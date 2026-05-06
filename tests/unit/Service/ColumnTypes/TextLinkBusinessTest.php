<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TextLinkBusinessTest extends TestCase {

	private TextLinkBusiness $textLink;
	private Column $column;

	public function setUp(): void {
		$this->textLink = new TextLinkBusiness(
			$this->createMock(LoggerInterface::class),
			$this->createMock(IL10N::class)
		);

		$this->column = $this->createMock(Column::class);
	}

	public function testCanBeParsed() {
		self::assertTrue($this->textLink->canBeParsed(null));
		self::assertTrue($this->textLink->canBeParsed(''));
		self::assertFalse($this->textLink->canBeParsed('null'));
		self::assertFalse($this->textLink->canBeParsed('invalidurl'));

		self::assertTrue($this->textLink->canBeParsed('https://nextcloud.com'));

		self::assertTrue($this->textLink->canBeParsed('test (https://nextcloud.com)'));
		self::assertTrue($this->textLink->canBeParsed('https://nextcloud.com (https://nextcloud.com)'));

		$column = new Column();
		self::assertFalse($this->textLink->canBeParsed(json_encode([
			'unknown' => 'https://nextcloud.com'
		]), $column));
		self::assertTrue($this->textLink->canBeParsed(json_encode([
			'resourceUrl' => 'https://nextcloud.com'
		]), $column));
		self::assertTrue($this->textLink->canBeParsed(json_encode([
			'value' => 'https://nextcloud.com'
		]), $column));
	}

	public function testParseValue() {
		self::assertEquals('', $this->textLink->parseValue(null));
		self::assertEquals('', $this->textLink->parseValue(''));
		self::assertEquals('', $this->textLink->parseValue('null'));

		self::assertEquals(json_encode(json_encode([
			'title' => 'https://nextcloud.com',
			'value' => 'https://nextcloud.com',
			'providerId' => 'url',
		])), $this->textLink->parseValue('https://nextcloud.com'));

		self::assertEquals(json_encode(json_encode([
			'title' => 'https://nextcloud.com',
			'value' => 'https://nextcloud.com',
			'providerId' => 'url',
		])), $this->textLink->parseValue('https://nextcloud.com (https://nextcloud.com)'));
		self::assertEquals(json_encode(json_encode([
			'title' => 'test',
			'value' => 'https://nextcloud.com',
			'providerId' => 'url',
		])), $this->textLink->parseValue('test (https://nextcloud.com)'));

		$column = new Column();
		self::assertEquals('', $this->textLink->parseValue(json_encode([
			'unknown' => 'https://nextcloud.com'
		]), $column));
		self::assertEquals(json_encode(json_encode([
			'title' => 'https://nextcloud.com',
			'value' => 'https://nextcloud.com',
			'providerId' => 'url',
		])), $this->textLink->parseValue(json_encode([
			'resourceUrl' => 'https://nextcloud.com'
		]), $column));
		self::assertEquals(json_encode(json_encode([
			'title' => 'Test link',
			'value' => 'https://nextcloud.com',
			'providerId' => 'url',
		])), $this->textLink->parseValue(json_encode([
			'title' => 'Test link',
			'value' => 'https://nextcloud.com',
			'providerId' => 'url',
		]), $column));
	}

	public function testValidateValue() {
		// Assert that no exception is thrown for valid values
		try {
			$this->textLink->validateValue(json_encode([
				'title' => 'Test link',
				'value' => 'https://nextcloud.com',
				'providerId' => 'url',
			]), $this->column, 'userId', 1, null);
		} catch (\Exception $e) {
			$this->fail('validateValue threw an exception for valid input: ' . $e->getMessage());
		}

		// Assert that exception is thrown for invalid values
		$this->expectException(\OCA\Tables\Errors\BadRequestError::class);
		$this->textLink->validateValue(json_encode([
			'title' => 'Test link',
			'value' => 'invalidurl',
			'providerId' => 'url',
		]), $this->column, 'userId', 1, null);
		$this->expectException(\OCA\Tables\Errors\BadRequestError::class);
		$this->textLink->validateValue(json_encode([
			'title' => 'Test link',
			'value' => 'javascript:https://nextcloud.com',
			'providerId' => 'url',
		]), $this->column, 'userId', 1, null);
	}
}
