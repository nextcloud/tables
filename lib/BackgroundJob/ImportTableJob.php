<?php

namespace OCA\Tables\BackgroundJob;

use OCA\Tables\Service\ImportService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;

class ImportTableJob extends QueuedJob {
	public function __construct(ITimeFactory $time, private ImportService $importService)
	{
		parent::__construct($time);
	}

	/**
	 * @param array $argument
	 */
	public function run($argument): void
	{
		$userId = $argument['user_id'];
		$tableId = $argument['table_id'];
		$viewId = $argument['view_id'];
		$path = $argument['path'];
		$createMissingColumns = $argument['create_missing_columns'] ?? false;
		$columnsConfig = $argument['columns_config'] ?? null;

		$result = $this->importService->import($userId, $tableId, $viewId, $path, $createMissingColumns, $columnsConfig);

		//fixme: handle errors
		//fixme: trigger a notification for the import
	}
}
