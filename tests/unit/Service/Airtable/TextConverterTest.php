<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service\Airtable;

use OCA\Tables\Service\Airtable\ColumnTypes\EmailConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\MultilineTextConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\PhoneConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\RichTextConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\SingleLineTextConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\TextConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\UrlConverter;
use PHPUnit\Framework\TestCase;

/**
 * Tests for all plain-text converters (text, singleLineText, multilineText,
 * richText, url, email, phone).
 *
 * These converters share the same behaviour:
 *   - Return a ColumnDto with type='text' and the correct subtype
 *   - Pass string values through unchanged
 *   - Return null for null or empty-string input
 *   - Emit no report rows
 */
class TextConverterTest extends TestCase {

	/** @return array<string, array{object, string, string}> [converter, airtableType, expectedSubtype] */
	public function textConverterProvider(): array {
		return [
			'text'            => [new TextConverter(),          'text',           'line'],
			'singleLineText'  => [new SingleLineTextConverter(), 'singleLineText', 'line'],
			'multilineText'   => [new MultilineTextConverter(), 'multilineText',  'long'],
			'richText'        => [new RichTextConverter(),      'richText',       'rich'],
			'url'             => [new UrlConverter(),           'url',            'link'],
			'email'           => [new EmailConverter(),         'email',          'line'],
			'phone'           => [new PhoneConverter(),         'phone',          'line'],
		];
	}

	/** @dataProvider textConverterProvider */
	public function testGetAirtableType(object $converter, string $expectedType, string $expectedSubtype): void {
		$this->assertSame($expectedType, $converter->getAirtableType());
	}

	/** @dataProvider textConverterProvider */
	public function testToTablesColumnSubtype(object $converter, string $expectedType, string $expectedSubtype): void {
		$col = ['name' => 'Field'];
		$report = [];

		$dto = $converter->toTablesColumn($col, $report);

		$this->assertNotNull($dto, "toTablesColumn() must not return null for {$expectedType}");
		$this->assertSame('text', $dto->type);
		$this->assertSame($expectedSubtype, $dto->subtype);
		$this->assertEmpty($report, "No report rows expected for {$expectedType}");
	}

	/** @dataProvider textConverterProvider */
	public function testToTablesValuePassthroughString(object $converter, string $expectedType, string $expectedSubtype): void {
		$report = [];
		$result = $converter->toTablesValue('hello world', [], $report);
		$this->assertSame('hello world', $result);
		$this->assertEmpty($report);
	}

	/** @dataProvider textConverterProvider */
	public function testToTablesValueNullReturnsNull(object $converter, string $expectedType, string $expectedSubtype): void {
		$report = [];
		$this->assertNull($converter->toTablesValue(null, [], $report));
	}

	/** @dataProvider textConverterProvider */
	public function testToTablesValueEmptyStringReturnsNull(object $converter, string $expectedType, string $expectedSubtype): void {
		$report = [];
		$this->assertNull($converter->toTablesValue('', [], $report));
	}
}
