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

	private TextLinkBusiness $textLinkBusiness;
	private Column $column;

	public function setUp(): void {
		$this->textLinkBusiness = new TextLinkBusiness(
			$this->createMock(LoggerInterface::class),
			$this->createMock(IL10N::class)
		);

		$this->column = $this->createMock(Column::class);
	}

	public function testCanBeParsed() {
		self::assertTrue($this->textLinkBusiness->canBeParsed(null, $this->column));
		self::assertTrue($this->textLinkBusiness->canBeParsed('', $this->column));
		self::assertFalse($this->textLinkBusiness->canBeParsed('null', $this->column));
		self::assertFalse($this->textLinkBusiness->canBeParsed('invalidurl', $this->column));

		self::assertTrue($this->textLinkBusiness->canBeParsed('https://nextcloud.com', $this->column));

		self::assertTrue($this->textLinkBusiness->canBeParsed('test (https://nextcloud.com)', $this->column));
		self::assertTrue($this->textLinkBusiness->canBeParsed('https://nextcloud.com (https://nextcloud.com)', $this->column));

		$column = new Column();
		self::assertFalse($this->textLinkBusiness->canBeParsed(json_encode([
			'unknown' => 'https://nextcloud.com'
		]), $column));
		self::assertTrue($this->textLinkBusiness->canBeParsed(json_encode([
			'resourceUrl' => 'https://nextcloud.com'
		]), $column));
		self::assertTrue($this->textLinkBusiness->canBeParsed(json_encode([
			'value' => 'https://nextcloud.com'
		]), $column));
	}

	public function testParseValue() {
		self::assertEquals('', $this->textLinkBusiness->parseValue(null, $this->column));
		self::assertEquals('', $this->textLinkBusiness->parseValue('', $this->column));
		self::assertEquals('', $this->textLinkBusiness->parseValue('null', $this->column));

		self::assertEquals(json_encode(json_encode([
			'title' => 'https://nextcloud.com',
			'value' => 'https://nextcloud.com',
			'providerId' => 'url',
		])), $this->textLinkBusiness->parseValue('https://nextcloud.com', $this->column));

		self::assertEquals(json_encode(json_encode([
			'title' => 'https://nextcloud.com',
			'value' => 'https://nextcloud.com',
			'providerId' => 'url',
		])), $this->textLinkBusiness->parseValue('https://nextcloud.com (https://nextcloud.com)', $this->column));
		self::assertEquals(json_encode(json_encode([
			'title' => 'test',
			'value' => 'https://nextcloud.com',
			'providerId' => 'url',
		])), $this->textLinkBusiness->parseValue('test (https://nextcloud.com)', $this->column));

		$column = new Column();
		self::assertEquals('', $this->textLinkBusiness->parseValue(json_encode([
			'unknown' => 'https://nextcloud.com'
		]), $column));
		self::assertEquals(json_encode(json_encode([
			'title' => 'https://nextcloud.com',
			'value' => 'https://nextcloud.com',
			'providerId' => 'url',
		])), $this->textLinkBusiness->parseValue(json_encode([
			'resourceUrl' => 'https://nextcloud.com'
		]), $column));
		self::assertEquals(json_encode(json_encode([
			'title' => 'Test link',
			'value' => 'https://nextcloud.com',
			'providerId' => 'url',
		])), $this->textLinkBusiness->parseValue(json_encode([
			'title' => 'Test link',
			'value' => 'https://nextcloud.com',
			'providerId' => 'url',
		]), $column));
	}

	public function testValidateValue() {
		// Assert that no exception is thrown for valid values
		try {
			$this->textLinkBusiness->validateValue(json_encode([
				'title' => 'Test link',
				'value' => 'https://nextcloud.com',
				'providerId' => 'url',
			]), $this->column, 'userId', 1, null);
		} catch (\Exception $e) {
			$this->fail('validateValue threw an exception for valid input: ' . $e->getMessage());
		}

		// Assert that exception is thrown for invalid values
		$this->expectException(\OCA\Tables\Errors\BadRequestError::class);
		$this->textLinkBusiness->validateValue(json_encode([
			'title' => 'Test link',
			'value' => 'invalidurl',
			'providerId' => 'url',
		]), $this->column, 'userId', 1, null);
		$this->expectException(\OCA\Tables\Errors\BadRequestError::class);
		$this->textLinkBusiness->validateValue(json_encode([
			'title' => 'Test link',
			'value' => 'javascript:https://nextcloud.com',
			'providerId' => 'url',
		]), $this->column, 'userId', 1, null);
	}
}
