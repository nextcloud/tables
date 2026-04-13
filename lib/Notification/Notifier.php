<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Notification;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\BackgroundJob\AirtableImportJob;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

/**
 * Renders Nextcloud notifications sent by the Airtable import background job.
 *
 * Handled subjects (defined in AirtableImportJob):
 *   - `airtable_import_done`   — import completed successfully
 *   - `airtable_import_failed` — import stopped due to an error
 */
class Notifier implements INotifier {

	public function __construct(
		private readonly IFactory      $l10nFactory,
		private readonly IURLGenerator $urlGenerator,
	) {
	}

	// =========================================================================
	// INotifier
	// =========================================================================

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l10nFactory->get(Application::APP_ID)->t('Tables');
	}

	/**
	 * @throws UnknownNotificationException  When the notification does not belong to this app / notifier.
	 * @throws AlreadyProcessedException     When the referenced import job no longer exists.
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new UnknownNotificationException('Not a Tables notification');
		}

		$subject = $notification->getSubject();

		if ($subject === AirtableImportJob::NOTIFICATION_SUBJECT_DONE) {
			return $this->prepareDone($notification, $languageCode);
		}

		if ($subject === AirtableImportJob::NOTIFICATION_SUBJECT_FAILED) {
			return $this->prepareFailed($notification, $languageCode);
		}

		throw new UnknownNotificationException('Unknown Tables notification subject: ' . $subject);
	}

	// =========================================================================
	// Private helpers
	// =========================================================================

	private function prepareDone(INotification $notification, string $languageCode): INotification {
		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);

		$notification->setParsedSubject(
			$l->t('Airtable import finished successfully.')
		);

		$notification->setIcon(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
		);

		return $notification;
	}

	private function prepareFailed(INotification $notification, string $languageCode): INotification {
		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);

		$params        = $notification->getSubjectParameters();
		$errorMessage  = (string) ($params['error'] ?? '');

		if ($errorMessage !== '') {
			$parsedSubject = $l->t('Airtable import failed: %s', [$errorMessage]);
		} else {
			$parsedSubject = $l->t('Airtable import failed.');
		}

		$notification->setParsedSubject($parsedSubject);

		$notification->setIcon(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
		);

		return $notification;
	}
}
