<?php

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\Entity;

interface IRowCell {

	public function setRowIdWrapper(int $rowId);

	public function setColumnIdWrapper(int $columnId);

	/**
	 * @param mixed $value
	 */
	public function setValueWrapper($value);

}
