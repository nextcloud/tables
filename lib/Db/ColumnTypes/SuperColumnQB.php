<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db\ColumnTypes;

use OCA\Tables\Db\Column;
use OCA\Tables\Errors\InternalError;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\QueryBuilder\IQueryFunction;
use Psr\Log\LoggerInterface;

class SuperColumnQB implements IColumnTypeQB {
	protected LoggerInterface $logger;
	protected int $platform;

	public function __construct(LoggerInterface $logger) {
		$this->logger = $logger;
	}

	public function setPlatform(int $platform): void {
		$this->platform = $platform;
	}

	public function formatCellValue(string $unformattedValue): string {
		if ($this->platform === self::DB_PLATFORM_PGSQL) {
			return 'LOWER(' . $unformattedValue . ')';
		} elseif ($this->platform === self::DB_PLATFORM_SQLITE) {
			return $unformattedValue;
		} else { // mariadb / mysql
			return 'JSON_UNQUOTE(LOWER(' . $unformattedValue . '))';
		}
	}

	public function passSearchValue(IQueryBuilder $qb, string $unformattedSearchValue, string $operator, string $searchValuePlaceHolder): void {
		$lowerCaseSearchValue = strtolower($unformattedSearchValue);
		switch ($operator) {
			case 'begins-with':
				$lowerCaseSearchValue = $lowerCaseSearchValue . '%';
				break;
			case 'ends-with':
				$lowerCaseSearchValue = '%' . $lowerCaseSearchValue;
				break;
			case 'contains':
				$lowerCaseSearchValue = '%' . $lowerCaseSearchValue . '%';
				break;
			default:
				break;
		}
		$qb->setParameter($searchValuePlaceHolder, $lowerCaseSearchValue, IQueryBuilder::PARAM_STR);
	}

	/**
	 * @param string $operator
	 * @param string $formattedCellValue
	 * @param string $searchValuePlaceHolder
	 * @return string
	 * @throws InternalError
	 */
	private function sqlFilterOperation(string $operator, string $formattedCellValue, string $searchValuePlaceHolder) : string {
		switch ($operator) {
			case 'begins-with':
			case 'ends-with':
			case 'contains':
				return $formattedCellValue . ' LIKE :' . $searchValuePlaceHolder;
			case 'does-not-contain':
				return $formattedCellValue . ' NOT LIKE :' . $searchValuePlaceHolder;
			case 'is-equal':
				return $formattedCellValue . ' = :' . $searchValuePlaceHolder;
			case 'is-not-equal':
				return $formattedCellValue . ' != :' . $searchValuePlaceHolder;
			case 'is-greater-than':
				return $formattedCellValue . ' > :' . $searchValuePlaceHolder;
			case 'is-greater-than-or-equal':
				return $formattedCellValue . ' >= :' . $searchValuePlaceHolder;
			case 'is-lower-than':
				return $formattedCellValue . ' < :' . $searchValuePlaceHolder;
			case 'is-lower-than-or-equal':
				return $formattedCellValue . ' <= :' . $searchValuePlaceHolder;
			case 'is-empty':
				return $formattedCellValue . ' = \'\' OR ' . $formattedCellValue . ' IS NULL';
			default:
				throw new InternalError('Operator ' . $operator . ' is not supported.');
		}
	}

	private function getFormattedDataCellValue(string $columnPlaceHolder, int $columnId): string {
		if ($this->platform === self::DB_PLATFORM_PGSQL) {
			$cellValue = 'c' . $columnId . ' ->> \'value\'';
		} elseif ($this->platform === self::DB_PLATFORM_SQLITE) {
			$cellValue = 'json_extract(t2.value, "$.columnId") = ' . $columnId . ' AND LOWER(json_extract(t2.value, "$.value"))';
		} else {
			$cellValue = 'JSON_EXTRACT(data, CONCAT( JSON_UNQUOTE(JSON_SEARCH(JSON_EXTRACT(data, \'$[*].columnId\'), \'one\', :' . $columnPlaceHolder . ')), \'.value\'))';
		}

		return $this->formatCellValue($cellValue);
	}

	/**
	 * @param int $metaId
	 * @return string
	 * @throws InternalError
	 */
	public static function getMetaColumnName(int $metaId): string {
		switch ($metaId) {
			case Column::TYPE_META_ID:
				return 'id';
			case Column::TYPE_META_CREATED_BY:
				return 'created_by';
			case Column::TYPE_META_UPDATED_BY:
				return 'last_edit_by';
			case Column::TYPE_META_CREATED_AT:
				return 'created_at';
			case Column::TYPE_META_UPDATED_AT:
				return 'last_edit_at';
			default:
				throw new InternalError('No meta data column exists with id ' . $metaId);
		}
	}

	/**
	 * @param IQueryBuilder $qb
	 * @param array $filter
	 * @param string $filterId
	 * @return IQueryFunction
	 * @throws InternalError
	 */
	public function addWhereFilterExpression(IQueryBuilder $qb, array $filter, string $filterId): IQueryFunction {
		$searchValuePlaceHolder = 'searchValue' . $filterId; // qb parameter binding name
		$this->passSearchValue($qb, $filter['value'], $filter['operator'], $searchValuePlaceHolder);
		$columnPlaceHolder = 'column' . $filterId; // qb parameter binding name
		if ($filter['columnId'] < 0) { // negative ids for meta data columns
			return $qb->createFunction($this->sqlFilterOperation($filter['operator'], $this->getMetaColumnName($filter['columnId']), $searchValuePlaceHolder));
		}

		$qb->setParameter($columnPlaceHolder, $filter['columnId'], IQueryBuilder::PARAM_INT);
		$formattedCellValue = $this->getFormattedDataCellValue($columnPlaceHolder, $filter['columnId']); // as sql string
		$filterOperation = $this->sqlFilterOperation($filter['operator'], $formattedCellValue, $searchValuePlaceHolder);

		if ($this->platform === self::DB_PLATFORM_PGSQL) {
			$sqlFilterString = $filterOperation;
		} elseif ($this->platform === self::DB_PLATFORM_SQLITE) {
			$qb->from($qb->createFunction('json_each(data) as t2'));
			$sqlFilterString = $filterOperation;
		} else { // mariadb / mysql
			$sqlFilterString = $filterOperation;
		}
		return $qb->createFunction($sqlFilterString);
	}

	public function addWhereForFindAllWithColumn(IQueryBuilder $qb, int $columnId): void {
		if ($this->platform === self::DB_PLATFORM_PGSQL) {
			// due to errors using doctrine with json, I paste the columnId inline.
			// columnId is a number, ensured by the parameter definition
			$qb->where('data::jsonb @> \'[{"columnId": ' . $columnId . '}]\'::jsonb');
		} elseif ($this->platform === self::DB_PLATFORM_SQLITE) {
			$qb->from($qb->createFunction('json_each(data)'));
			$qb->where('json_extract(value, "$.columnId") = :columnId');
		} else {
			$qb->where('JSON_CONTAINS(JSON_EXTRACT(data, \'$[*].columnId\'), :columnId, \'$\') = 1');
		}
		$qb->setParameter('columnId', $columnId);
	}

}
