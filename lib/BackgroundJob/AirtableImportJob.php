<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\BackgroundJob;

use DateTime;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Service\Airtable\AirtableDataImporter;
use OCA\Tables\Service\Airtable\AirtableFetcher;
use OCA\Tables\Service\Airtable\AirtableImportReportBuilder;
use OCA\Tables\Service\Airtable\AirtableSchemaConverter;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;

/**
 * Background job that drives a single Airtable import session to completion.
 *
 * The job is enqueued by AirtableImportController and receives the ID of the
 * matching row in oc_tables_airtable_imports as its primary argument.  It
 * updates the status column of that row throughout its lifetime so that the
 * frontend can poll for progress via the status endpoint.
 *
 * Orchestration (high-level):
 *   1. Mark job as "running" in the DB.
 *   2. Fetch the Airtable schema           (AirtableFetcher).
 *   3. Convert schema → Tables columns     (AirtableSchemaConverter).
 *   4. Paginate and import row data        (AirtableDataImporter).
 *   5. Build the import-report table       (AirtableImportReportBuilder).
 *   6. Mark job as "finished" and notify the user.
 *
 * On any unhandled exception the job marks itself "failed", stores the error
 * message in the DB, and sends a failure notification to the user.
 */
class AirtableImportJob extends QueuedJob {

	public const STATUS_PENDING  = 'pending';
	public const STATUS_RUNNING  = 'running';
	public const STATUS_FINISHED = 'finished';
	public const STATUS_FAILED   = 'failed';

	/** Notification subject sent on successful completion. */
	public const NOTIFICATION_SUBJECT_DONE   = 'airtable_import_done';
	/** Notification subject sent on failure. */
	public const NOTIFICATION_SUBJECT_FAILED = 'airtable_import_failed';

	public function __construct(
		ITimeFactory $time,
		private readonly IDBConnection $db,
		private readonly INotificationManager $notificationManager,
		private readonly LoggerInterface $logger,
		private readonly AirtableFetcher $fetcher,
		private readonly AirtableSchemaConverter $schemaConverter,
		private readonly AirtableDataImporter $dataImporter,
		private readonly AirtableImportReportBuilder $reportBuilder,
	) {
		parent::__construct($time);
	}

	/**
	 * @param array{job_id: int, user_id: string, session_cookie?: string|null} $argument
	 */
	protected function run(mixed $argument): void {
		$jobId          = (int) ($argument['job_id'] ?? 0);
		$userId         = (string) ($argument['user_id'] ?? '');
		$sessionCookie  = $argument['session_cookie'] ?? null;

		if ($jobId === 0 || $userId === '') {
			$this->logger->error('AirtableImportJob: started with invalid arguments', [
				'app'      => Application::APP_ID,
				'argument' => $argument,
			]);
			return;
		}

		$row = $this->fetchJobRow($jobId);
		if ($row === null) {
			$this->logger->error('AirtableImportJob: job row not found', [
				'app'    => Application::APP_ID,
				'job_id' => $jobId,
			]);
			return;
		}

		$this->setStatus($jobId, self::STATUS_RUNNING);
		$this->logger->info('AirtableImportJob: starting import', [
			'app'     => Application::APP_ID,
			'job_id'  => $jobId,
			'user_id' => $userId,
		]);

		try {
			$shareUrl    = (string) $row['share_url'];
			$reportRows  = [];

			// 1. Fetch Airtable schema + request metadata for row fetching.
			$fetchResult    = $this->fetcher->fetchAll($shareUrl, $sessionCookie ?: null);
			$schema         = $fetchResult['schema'];
			$appId          = $fetchResult['appId'];
			$requestHeaders = $fetchResult['requestHeaders'];
			$cookies        = $fetchResult['cookies'];

			// 2. Convert schema to Nextcloud Tables tables + columns.
			$conversionResult = $this->schemaConverter->convert($schema, $userId, $reportRows);
			$tableMapping     = $conversionResult['tableMapping'];
			$importedTableIds = $conversionResult['importedTableIds'];

			// Set progress total based on table count as a rough estimate;
			// AirtableDataImporter increments progress_done per row.
			$this->setProgressTotal($jobId, count($tableMapping));

			// 3. Paginate and bulk-insert row data.
			$this->dataImporter->import(
				$appId,
				$tableMapping,
				$requestHeaders,
				$cookies,
				$jobId,
				$userId,
				$reportRows,
			);

			// 4. Create the import-report table (only if there were issues).
			$this->reportBuilder->build($reportRows, $userId, $jobId);

			$this->setStatus($jobId, self::STATUS_FINISHED);
			$this->logger->info('AirtableImportJob: import finished', [
				'app'    => Application::APP_ID,
				'job_id' => $jobId,
			]);

			$this->notify($userId, self::NOTIFICATION_SUBJECT_DONE, [
				'job_id'    => $jobId,
				'table_ids' => implode(',', $importedTableIds),
			]);
		} catch (\Throwable $e) {
			$this->logger->error('AirtableImportJob: import failed', [
				'app'       => Application::APP_ID,
				'job_id'    => $jobId,
				'exception' => $e,
			]);
			$this->setStatus($jobId, self::STATUS_FAILED, $e->getMessage());
			$this->notify($userId, self::NOTIFICATION_SUBJECT_FAILED, [
				'job_id' => $jobId,
				'error'  => $e->getMessage(),
			]);
		}
	}

	/** @return array<string, mixed>|null */
	private function fetchJobRow(int $jobId): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('tables_airtable_imports')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($jobId, IQueryBuilder::PARAM_INT)));

		$result = $qb->executeQuery();
		$row    = $result->fetch();
		$result->closeCursor();

		return $row !== false ? $row : null;
	}

	private function setStatus(int $jobId, string $status, ?string $errorMessage = null): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('tables_airtable_imports')
			->set('status',     $qb->createNamedParameter($status))
			->set('updated_at', $qb->createNamedParameter(new DateTime('now'), IQueryBuilder::PARAM_DATE))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($jobId, IQueryBuilder::PARAM_INT)));

		if ($errorMessage !== null) {
			$qb->set('error_message', $qb->createNamedParameter($errorMessage));
		}

		$qb->executeStatement();
	}

	private function setProgressTotal(int $jobId, int $total): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('tables_airtable_imports')
			->set('progress_total', $qb->createNamedParameter($total, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($jobId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	private function notify(string $userId, string $subject, array $params): void {
		$notification = $this->notificationManager->createNotification();
		$notification
			->setApp(Application::APP_ID)
			->setUser($userId)
			->setDateTime(new DateTime('now'))
			->setObject('airtable_import', (string) ($params['job_id'] ?? 0))
			->setSubject($subject, $params);

		try {
			$this->notificationManager->notify($notification);
		} catch (\InvalidArgumentException $e) {
			$this->logger->debug('AirtableImportJob: notification skipped', [
				'app'     => Application::APP_ID,
				'subject' => $subject,
			]);
		}
	}
}
