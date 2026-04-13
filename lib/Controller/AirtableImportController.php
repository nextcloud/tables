<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Controller;

use DateTime;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\BackgroundJob\AirtableImportJob;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\BackgroundJob\IJobList;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * HTTP controller for the Airtable import feature.
 *
 * Three endpoints:
 *
 *   POST   /api/1/import/airtable
 *     Validate the share URL, create a pending job row in
 *     oc_tables_airtable_imports, enqueue the background job, and return the
 *     job ID so the frontend can start polling.
 *
 *   GET    /api/1/import/airtable/{jobId}/status
 *     Return the current status, progress counters, and error message (if any)
 *     for the given job.  Only the owning user may read a job.
 *
 *   DELETE /api/1/import/airtable/{jobId}
 *     Cancel a pending or running job.  The background job checks the status
 *     at the start of each table import and aborts when it sees `cancelled`.
 */
class AirtableImportController extends Controller {

	use Errors;

	private const STATUS_PENDING   = AirtableImportJob::STATUS_PENDING;
	private const STATUS_RUNNING   = AirtableImportJob::STATUS_RUNNING;
	private const STATUS_FINISHED  = AirtableImportJob::STATUS_FINISHED;
	private const STATUS_FAILED    = AirtableImportJob::STATUS_FAILED;
	private const STATUS_CANCELLED = 'cancelled';

	/** Statuses from which a cancel transition is allowed. */
	private const CANCELLABLE_STATUSES = [self::STATUS_PENDING, self::STATUS_RUNNING];

	public function __construct(
		IRequest $request,
		private readonly IDBConnection $db,
		private readonly IJobList $jobList,
		private readonly string $userId,
		protected LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	// =========================================================================
	// POST /api/1/import/airtable
	// =========================================================================

	/**
	 * Start a new Airtable import job.
	 *
	 * Accepts a JSON body with:
	 *   - `shareUrl`      (string, required)  — public Airtable share URL
	 *   - `sessionCookie` (string, optional)  — `__Host-airtable-session` value
	 *                                           for private bases
	 *
	 * Returns `{'jobId': int, 'status': 'pending'}` on success.
	 *
	 * @param string      $shareUrl      Public Airtable share URL.
	 * @param string|null $sessionCookie Optional session cookie for private bases.
	 *
	 * @return DataResponse<Http::STATUS_OK, array{jobId: int, status: string}, array{}>
	 *        |DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *        |DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 5, period: 60)]
	public function startImport(string $shareUrl, ?string $sessionCookie = null): DataResponse {
		return $this->handleError(function () use ($shareUrl, $sessionCookie) {
			$shareUrl = trim($shareUrl);
			if ($shareUrl === '') {
				return new DataResponse(['message' => 'shareUrl must not be empty.'], Http::STATUS_BAD_REQUEST);
			}

			$now   = new DateTime();
			$nowStr = $now->format('Y-m-d H:i:s');

			// Persist job row.
			$qb = $this->db->getQueryBuilder();
			$qb->insert('tables_airtable_imports')
				->values([
					'user_id'    => $qb->createNamedParameter($this->userId),
					'status'     => $qb->createNamedParameter(self::STATUS_PENDING),
					'share_url'  => $qb->createNamedParameter($shareUrl),
					'created_at' => $qb->createNamedParameter($nowStr),
					'updated_at' => $qb->createNamedParameter($nowStr),
				]);
			$qb->executeStatement();

			$jobId = (int) $this->db->lastInsertId('*PREFIX*tables_airtable_imports');

			// Enqueue background job.
			$this->jobList->add(AirtableImportJob::class, [
				'job_id'         => $jobId,
				'user_id'        => $this->userId,
				'session_cookie' => $sessionCookie,
			]);

			$this->logger->info('AirtableImportController: import job enqueued', [
				'app'    => Application::APP_ID,
				'job_id' => $jobId,
				'user'   => $this->userId,
			]);

			return ['jobId' => $jobId, 'status' => self::STATUS_PENDING];
		});
	}

	// =========================================================================
	// GET /api/1/import/airtable/{jobId}/status
	// =========================================================================

	/**
	 * Return the current status of an import job.
	 *
	 * @param int $jobId
	 *
	 * @return DataResponse<Http::STATUS_OK, array{jobId: int, status: string, progressDone: int, progressTotal: int, errorMessage: string|null}, array{}>
	 *        |DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *        |DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 */
	#[NoAdminRequired]
	public function getStatus(int $jobId): DataResponse {
		return $this->handleError(function () use ($jobId) {
			$row = $this->fetchOwnedJobRow($jobId);
			if ($row === null) {
				return new DataResponse(['message' => 'Import job not found.'], Http::STATUS_NOT_FOUND);
			}

			return $this->formatJobRow($row);
		});
	}

	// =========================================================================
	// DELETE /api/1/import/airtable/{jobId}
	// =========================================================================

	/**
	 * Cancel a pending or running import job.
	 *
	 * The background job checks the DB status before processing each table and
	 * will abort when it encounters `cancelled`.
	 *
	 * @param int $jobId
	 *
	 * @return DataResponse<Http::STATUS_OK, array{jobId: int, status: string, progressDone: int, progressTotal: int, errorMessage: string|null}, array{}>
	 *        |DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *        |DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *        |DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 */
	#[NoAdminRequired]
	public function cancelImport(int $jobId): DataResponse {
		return $this->handleError(function () use ($jobId) {
			$row = $this->fetchOwnedJobRow($jobId);
			if ($row === null) {
				return new DataResponse(['message' => 'Import job not found.'], Http::STATUS_NOT_FOUND);
			}

			if (!in_array($row['status'], self::CANCELLABLE_STATUSES, true)) {
				return new DataResponse(
					['message' => 'Job cannot be cancelled in status "' . $row['status'] . '".'],
					Http::STATUS_BAD_REQUEST,
				);
			}

			$now = (new DateTime())->format('Y-m-d H:i:s');
			$qb  = $this->db->getQueryBuilder();
			$qb->update('tables_airtable_imports')
				->set('status',     $qb->createNamedParameter(self::STATUS_CANCELLED))
				->set('updated_at', $qb->createNamedParameter($now))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($jobId, IQueryBuilder::PARAM_INT)));
			$qb->executeStatement();

			$this->logger->info('AirtableImportController: import job cancelled', [
				'app'    => Application::APP_ID,
				'job_id' => $jobId,
				'user'   => $this->userId,
			]);

			$row['status']     = self::STATUS_CANCELLED;
			$row['updated_at'] = $now;

			return $this->formatJobRow($row);
		});
	}

	// =========================================================================
	// Private helpers
	// =========================================================================

	/**
	 * Fetch a job row from `oc_tables_airtable_imports` and verify that it
	 * belongs to the current user.  Returns null when not found or unauthorised.
	 *
	 * @return array<string, mixed>|null
	 */
	private function fetchOwnedJobRow(int $jobId): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('tables_airtable_imports')
			->where($qb->expr()->eq('id',      $qb->createNamedParameter($jobId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($this->userId)));

		$result = $qb->executeQuery();
		$row    = $result->fetch();
		$result->closeCursor();

		return $row !== false ? $row : null;
	}

	/**
	 * Format a raw DB row into the response shape exposed by the API.
	 *
	 * @param array<string, mixed> $row
	 * @return array{jobId: int, status: string, progressDone: int, progressTotal: int, errorMessage: string|null}
	 */
	private function formatJobRow(array $row): array {
		return [
			'jobId'         => (int) $row['id'],
			'status'        => (string) $row['status'],
			'progressDone'  => (int) $row['progress_done'],
			'progressTotal' => (int) $row['progress_total'],
			'errorMessage'  => $row['error_message'] !== null ? (string) $row['error_message'] : null,
		];
	}
}
