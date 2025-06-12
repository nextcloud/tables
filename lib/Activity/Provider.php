<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Activity;

use OCA\Tables\AppInfo\Application;
use OCP\Activity\Exceptions\UnknownActivityException;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\Activity\IProvider;
use OCP\Comments\ICommentsManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;

class Provider implements IProvider {
	protected ?IL10N $l = null;

	public function __construct(
		protected IFactory $languageFactory,
		protected IURLGenerator $url,
		protected ICommentsManager $commentsManager,
		protected IUserManager $userManager,
		protected IManager $activityManager,
	) {
	}

	/**
	 * @param string $language
	 * @param IEvent $event
	 * @param IEvent|null $previousEvent
	 * @return IEvent
	 * @throws UnknownActivityException
	 */
	public function parse($language, IEvent $event, ?IEvent $previousEvent = null): IEvent {
		if ($event->getApp() !== Application::APP_ID) {
			throw new UnknownActivityException();
		}

		$this->l = $this->languageFactory->get(Application::APP_ID, $language);

		if ($event->getSubject() === ActivityConstants::SUBJECT_IMPORT_FINISHED) {
			//			$event->setParsedMessage($comment->getMessage())
			//				->setRichMessage($message, $mentions);

			$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app-dark.svg')));

			if ($this->activityManager->isFormattingFilteredObject()) {
				try {
					return $this->parseShortVersion($event);
				} catch (UnknownActivityException) {
					// Ignore and simply use the long version...
				}
			}

			return $this->parseLongVersion($event);
		}

		throw new UnknownActivityException();
	}

	/**
	 * @throws UnknownActivityException
	 */
	protected function parseShortVersion(IEvent $event): IEvent {
		$subjectParameters = $this->getSubjectParameters($event);

		if ($event->getSubject() === ActivityConstants::SUBJECT_IMPORT_FINISHED) {
			$event->setRichSubject($this->l->t('You commented'), []);
		} else {
			throw new UnknownActivityException();
		}

		return $event;
	}

	/**
	 * @throws UnknownActivityException
	 */
	protected function parseLongVersion(IEvent $event): IEvent {
		$subjectParameters = $this->getSubjectParameters($event);

		if ($event->getSubject() === ActivityConstants::SUBJECT_IMPORT_FINISHED) {
			$event->setParsedSubject($this->l->t('You commented on %1$s', [
				$subjectParameters['filePath'],
			]))
				->setRichSubject($this->l->t('You commented on {file}'), [
					'file' => $this->generateFileParameter($subjectParameters['fileId'], $subjectParameters['filePath']),
				]);
		} else {
			throw new UnknownActivityException();
		}

		return $event;
	}

	protected function getSubjectParameters(IEvent $event): array {
		$subjectParameters = $event->getSubjectParameters();
		if (isset($subjectParameters['fileId'])) {
			return $subjectParameters;
		}

		return [
			'actor' => $subjectParameters[0],
			'fileId' => $event->getObjectId(),
			'filePath' => trim($subjectParameters[1], '/'),
		];
	}

	/**
	 * @return array<string, string>
	 */
	protected function generateFileParameter(int $id, string $path): array {
		return [
			'type' => 'file',
			'id' => (string)$id,
			'name' => basename($path),
			'path' => $path,
			'link' => $this->url->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $id]),
		];
	}
}
