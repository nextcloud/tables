<?php

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/** @template-extends QBMapper<PageContent> */
class PageContentMapper extends QBMapper {
	protected string $table = 'tables_contexts_page_content';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, PageContent::class);
	}
}
