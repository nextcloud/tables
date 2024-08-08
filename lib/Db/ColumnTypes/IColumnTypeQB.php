<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db\ColumnTypes;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\QueryBuilder\IQueryFunction;

interface IColumnTypeQB {
	public const DB_PLATFORM_MYSQL = 0;
	public const DB_PLATFORM_PGSQL = 1;
	public const DB_PLATFORM_SQLITE = 2;

	/**
	 * Set DB platform see self::*
	 *
	 * @param int $platform
	 * @return mixed
	 */
	public function setPlatform(int $platform);

	/**
	 * @param string $unformattedValue
	 * @return string
	 */
	public function formatCellValue(string $unformattedValue): string;

	/**
	 * @param IQueryBuilder $qb
	 * @param string $unformattedSearchValue
	 * @param string $operator
	 * @param string $searchValuePlaceHolder
	 * @return void
	 */
	public function passSearchValue(IQueryBuilder $qb, string $unformattedSearchValue, string $operator, string $searchValuePlaceHolder): void;

	/**
	 * @param IQueryBuilder $qb
	 * @param array $filter
	 * @param string $filterId
	 * @return IQueryFunction
	 */
	public function addWhereFilterExpression(IQueryBuilder $qb, array $filter, string $filterId): IQueryFunction;

	/**
	 * @param IQueryBuilder $qb
	 * @param int $columnId
	 * @return void
	 */
	public function addWhereForFindAllWithColumn(IQueryBuilder $qb, int $columnId): void;
}
