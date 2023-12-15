<?php

namespace OCA\Tables\Db;

interface IRowCell {

	public function setRowIdWrapper(int $rowId);

	public function setColumnIdWrapper(int $columnId);

	/**
	 * @param mixed $value
	 */
	public function setValueWrapper($value);

}
