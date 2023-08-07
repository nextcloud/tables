<?php

namespace OCA\Tables\Db\ColumnTypes;

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

	public function setPlatform(int $platform) {
		$this->platform = $platform;
	}

	public function formatCellValue(string $unformattedValue): string {
		if ($this->platform === self::DB_PLATFORM_PGSQL) {
			return 'LOWER('.$unformattedValue.')';
		} elseif ($this->platform === self::DB_PLATFORM_SQLITE) {
			// TODO DB BE SQLITE
			return '';
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
	private function buildSqlFilterString(string $operator, string $formattedCellValue, string $searchValuePlaceHolder, string $columnPlaceHolder = null) : string {
		if ($this->platform === self::DB_PLATFORM_PGSQL) {
			return "id IN (".
						"SELECT id ".
						"FROM oc_tables_rows, json_array_elements(data) as t1 ".
						"WHERE CAST(t1->>'columnId' AS int) = :".$columnPlaceHolder." AND ".$formattedCellValue." LIKE :".$searchValuePlaceHolder.
				")";
		} elseif ($this->platform === self::DB_PLATFORM_SQLITE) {
			// TODO DB BE SQLITE
			return '';
		} else { // mariadb / mysql
			switch ($operator) {
				case 'begins-with':
				case 'ends-with':
				case 'contains':
					return $formattedCellValue.' LIKE :'.$searchValuePlaceHolder;
				case 'is-equal':
					return $formattedCellValue.' = :'.$searchValuePlaceHolder;
				case 'is-greater-than':
					return $formattedCellValue.' > :'.$searchValuePlaceHolder;
				case 'is-greater-than-or-equal':
					return $formattedCellValue.' >= :'.$searchValuePlaceHolder;
				case 'is-lower-than':
					return $formattedCellValue.' < :'.$searchValuePlaceHolder;
				case 'is-lower-than-or-equal':
					return $formattedCellValue.' <= :'.$searchValuePlaceHolder;
				case 'is-empty':
					return $formattedCellValue.' = \'\' OR '.$formattedCellValue.' IS NULL';
				default:
					throw new InternalError('Operator '.$operator.' is not supported.');
			}
		}
	}

	private function getFormattedDataCellValue(string $columnPlaceHolder): string {
		if ($this->platform === self::DB_PLATFORM_PGSQL) {
			$cellValue = 't1 ->> \'value\'';
		} elseif ($this->platform === self::DB_PLATFORM_SQLITE) {
			// TODO DB BE SQLITE
		} else {
			$cellValue = 'JSON_EXTRACT(data, CONCAT( JSON_UNQUOTE(JSON_SEARCH(JSON_EXTRACT(data, \'$[*].columnId\'), \'one\', :'.$columnPlaceHolder.')), \'.value\'))';
		}

		return $this->formatCellValue($cellValue);
	}

	/**
	 * @param int $metaId
	 * @return string
	 * @throws InternalError
	 */
	private function getFormattedMetaDataCellValue(int $metaId): string {
		switch($metaId) {
			case -1: return 'id';
			case -2: return 'created_by';
			case -3: return 'last_edit_by';
			case -4: return 'created_at';
			case -5: return 'last_edit_at';
			default: throw new InternalError('No meta data column exists with id '.$metaId);
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
		$searchValuePlaceHolder = 'searchValue'.$filterId; // qb parameter binding name
		$columnPlaceHolder = 'column'.$filterId; // qb parameter binding name
		if($filter['columnId'] >= 0) { // negative ids for meta data columns
			$qb->setParameter($columnPlaceHolder, $filter['columnId'], IQueryBuilder::PARAM_INT);
			$formattedCellValue = $this->getFormattedDataCellValue($columnPlaceHolder); // as sql string
		} else {
			$formattedCellValue = $this->getFormattedMetaDataCellValue($filter['columnId']); // as sql string
		}

		$this->passSearchValue($qb, $filter['value'], $filter['operator'], $searchValuePlaceHolder);
		return $qb->createFunction($this->buildSqlFilterString($filter['operator'], $formattedCellValue, $searchValuePlaceHolder, $columnPlaceHolder));

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
