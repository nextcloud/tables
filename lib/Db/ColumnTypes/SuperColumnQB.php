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
		return 'JSON_UNQUOTE(LOWER('.$unformattedValue.'))';
	}
	public function passSearchValue(IQueryBuilder &$qb, string $unformattedSearchValue, string $operator, string $searchValuePlaceHolder): void	{
		$lowerCaseSearchValue = strtolower($unformattedSearchValue);
		switch ($operator) {
			case 'begins-with':
				$lowerCaseSearchValue = $lowerCaseSearchValue . '%';
				break;
			case 'ends-with':
				$lowerCaseSearchValue =  '%' . $lowerCaseSearchValue;
				break;
			case 'contains':
				$lowerCaseSearchValue =  '%' . $lowerCaseSearchValue . '%';
				break;
			default:
				break;
		}
		$qb->setParameter($searchValuePlaceHolder, $lowerCaseSearchValue, $qb::PARAM_STR);
	}

	/**
	 * @param string $operator
	 * @return string
	 * @throws InternalError
	 */
	private function buildSQLString(string $operator, string $columnPlaceHolder, string $searchValuePlaceHolder) : string{
		if ($this->platform === self::DB_PLATFORM_PGSQL) {
			return '';
		} elseif ($this->platform === self::DB_PLATFORM_SQLITE) {
			return '';
		} else {
			$cellValue = 'JSON_EXTRACT(data, CONCAT( JSON_UNQUOTE(JSON_SEARCH(JSON_EXTRACT(data, \'$[*].columnId\'), \'one\', :'.$columnPlaceHolder.')), \'.value\'))';
			$formattedCellValue = $this->formatCellValue($cellValue);
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
					return $formattedCellValue.' = \'\' OR :'.$searchValuePlaceHolder.' IS NULL';
				default:
					throw new InternalError('Operator '.$operator.' is not supported.');
			}
		}
	}

	public function addWhereFilterExpression(IQueryBuilder &$qb, array $filter, string $filterId): IQueryFunction {
		$searchValuePlaceHolder = 'searchValue'.$filterId;
		$columnPlaceHolder = 'column'.$filterId;
		$qb->setParameter($columnPlaceHolder, $filter['columnId'], $qb::PARAM_INT);
		$this->passSearchValue($qb, $filter['value'], $filter['operator'], $searchValuePlaceHolder);
		return $qb->createFunction($this->buildSQLString($filter['operator'], $columnPlaceHolder, $searchValuePlaceHolder));
	}

	public function addWhereForFindAllWithColumn(IQueryBuilder &$qb, int $columnId): void {
		if ($this->platform === self::DB_PLATFORM_PGSQL) {
			// due to errors using doctrine with json, I paste the columnId inline.
			// columnId is a number, ensured by the parameter definition
			$qb->where('data::jsonb @> \'[{"columnId": ' . $columnId . '}]\'::jsonb');
		} elseif ($this->platform === self::DB_PLATFORM_SQLITE) {
			$qb->from($qb->createFunction('json_each(data)'));
			$qb->where('json_extract(value, "$.columnId") = :columnId');
		} else {
			$qb->where('JSON_CONTAINS(JSON_VALUE(data, \'$.columnId\'), :columnId, \'$\') = 1');
		}
		$qb->setParameter('columnId', $columnId);
	}

}
