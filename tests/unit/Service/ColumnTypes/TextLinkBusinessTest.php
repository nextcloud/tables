<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TextLinkBusinessTest extends TestCase {

	private TextLinkBusiness $textLink;

	public function setUp(): void {
		$this->textLink = new TextLinkBusiness(
			$this->createMock(LoggerInterface::class)
		);
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
}
