<?php

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/** @template-extends QBMapper<Page> */
class PageMapper extends QBMapper {
	protected string $table = 'tables_contexts_page';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, Page::class);
	}
}
