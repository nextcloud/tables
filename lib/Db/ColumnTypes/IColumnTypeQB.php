<?php

namespace OCA\Tables\DB\ColumnTypes;

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
}
