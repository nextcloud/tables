<?php
/**
 * @copyright Copyright (c) 2024 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
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
