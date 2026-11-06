<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use InvalidArgumentException;
use JsonSerializable;
use OC\User\NoUserException;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Vendor\PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IL10N;
use Throwable;

/**
 * Describes a single problem encountered while importing a file.
 *
 * Serialized to a plain array shape when passed through HTTP responses,
 * activity subject parameters or notification parameters:
 * array{code: string, row: ?int, column: ?string, value: ?string, description: ?string}
 *
 * @psalm-import-type TablesImportProblem from ResponseDefinitions
 */
final class ImportProblem implements JsonSerializable {
	public const CODE_EMPTY_COLUMN_TITLE = 'empty_column_title';
	public const CODE_COLUMN_TITLE_GAP = 'column_title_gap';
	public const CODE_NO_COLUMNS_FOUND = 'no_columns_found';
	public const CODE_ID_NOT_NUMERIC = 'id_not_numeric';
	public const CODE_MISSING_MANDATORY = 'missing_mandatory';
	public const CODE_PARSE_FAILED = 'parse_failed';
	public const CODE_ROW_INSERT_FAILED = 'row_insert_failed';
	public const CODE_ROW_UPDATE_FAILED = 'row_update_failed';
	public const CODE_PERMISSION_DENIED = 'permission_denied';
	public const CODE_RESOURCE_NOT_FOUND = 'resource_not_found';
	public const CODE_FILE_READ_FAILED = 'file_read_failed';
	public const CODE_IMPORT_FAILED = 'import_failed';

	public function __construct(
		public string $code,
		public ?int $row = null,
		public ?string $column = null,
		public ?string $value = null,
		public ?string $description = null,
	) {
	}

	/**
	 * @return TablesImportProblem
	 */
	public function jsonSerialize(): array {
		return [
			'code' => $this->code,
			'row' => $this->row,
			'column' => $this->column,
			'value' => $this->value,
			'description' => $this->description,
		];
	}

	public static function codeForException(Throwable $exception): string {
		return match (true) {
			$exception instanceof NotFoundError, $exception instanceof NotFoundException, $exception instanceof NoUserException => self::CODE_RESOURCE_NOT_FOUND,
			$exception instanceof PermissionError, $exception instanceof NotPermittedException => self::CODE_PERMISSION_DENIED,
			$exception instanceof BadRequestError, $exception instanceof InvalidArgumentException, $exception instanceof ReaderException => self::CODE_FILE_READ_FAILED,
			default => self::CODE_IMPORT_FAILED,
		};
	}

	/**
	 * Render a single-line description of a problem, including the offending
	 * value where it helps identify the problem.
	 */
	public static function describeProblem(IL10N $l, string $code, ?string $value): string {
		if ($value === null || $value === '') {
			return self::describeCode($l, $code);
		}
		return match ($code) {
			self::CODE_ID_NOT_NUMERIC => $l->t('The ID value "%s" is not numeric', [$value]),
			self::CODE_PARSE_FAILED => $l->t('The value "%s" could not be parsed', [$value]),
			default => self::describeCode($l, $code),
		};
	}

	/**
	 * Render the reason for a problem code, without the row/column location.
	 */
	public static function describeCode(IL10N $l, string $code): string {
		return match ($code) {
			self::CODE_EMPTY_COLUMN_TITLE => $l->t('Column title is empty'),
			self::CODE_COLUMN_TITLE_GAP => $l->t('There is a gap in the column titles'),
			self::CODE_NO_COLUMNS_FOUND => $l->t('No matching columns were found'),
			self::CODE_ID_NOT_NUMERIC => $l->t('The ID value is not numeric'),
			self::CODE_MISSING_MANDATORY => $l->t('A mandatory value is missing'),
			self::CODE_PARSE_FAILED => $l->t('The value could not be parsed'),
			self::CODE_ROW_INSERT_FAILED => $l->t('The row could not be created'),
			self::CODE_ROW_UPDATE_FAILED => $l->t('The row could not be updated'),
			self::CODE_PERMISSION_DENIED => $l->t('Permission denied'),
			self::CODE_RESOURCE_NOT_FOUND => $l->t('A file, table, view, or row needed for the import could not be found.'),
			self::CODE_FILE_READ_FAILED => $l->t('The import file could not be read. Check its format and contents.'),
			self::CODE_IMPORT_FAILED => $l->t('An unexpected error occurred during the import. Please contact your administrator.'),
			default => $l->t('An unknown problem occurred'),
		};
	}
}
