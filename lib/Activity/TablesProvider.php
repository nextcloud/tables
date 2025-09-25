<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Activity;

use OCP\Activity\IEvent;
use OCP\Activity\IProvider;
use OCP\IURLGenerator;
use OCP\IUserManager;

class TablesProvider implements IProvider {

	public function __construct(
		private $userId,
		private IURLGenerator $urlGenerator,
		private ActivityManager $activityManager,
		private IUserManager $userManager,
	) {
	}

	public function parse($language, IEvent $event, ?IEvent $previousEvent = null): IEvent {
		if ($event->getApp() !== 'tables') {
			throw new \InvalidArgumentException();
		}

		$event = $this->setIcon($event);
		$subjectIdentifier = $event->getSubject();
		$subjectParams = $event->getSubjectParameters();
		$ownActivity = ($event->getAuthor() === $this->userId);

		/**
		 * Map stored parameter objects to rich string types
		 */
		$author = $event->getAuthor();
		$user = $this->userManager->get($author);

		if ($user !== null) {
			$params = [
				'user' => [
					'type' => 'user',
					'id' => $author,
					'name' => $user->getDisplayName()
				],
			];
			$event->setAuthor($author);
		} else {
			$params = [
				'user' => [
					'type' => 'user',
					'id' => 'deleted_users',
					'name' => 'deleted_users',
				]
			];
		}

		if ($event->getObjectType() === ActivityManager::TABLES_OBJECT_TABLE) {
			$table = [
				'type' => 'highlight',
				'id' => (string)$event->getObjectId(),
				'name' => $event->getObjectName(),
				'link' => $this->tablesUrl('/table/' . $event->getObjectId()),
			];
			$params['table'] = $table;
			$event->setLink($this->tablesUrl('/table/' . $event->getObjectId()));
		}

		if ($event->getObjectType() === ActivityManager::TABLES_OBJECT_ROW) {
			$table = [
				'type' => 'highlight',
				'id' => (string)$subjectParams['table']['id'],
				'name' => (string)$subjectParams['table']['title'],
				'link' => $this->tablesUrl('/table/' . $subjectParams['table']['id']),
			];
			$params['table'] = $table;
			$row = [
				'type' => 'highlight',
				'id' => (string)$event->getObjectId(),
				'name' => '#' . $event->getObjectId(),
				'link' => $this->tablesUrl('/table/' . $subjectParams['table']['id'] . '/row/' . $event->getObjectId()),
			];
			$params['row'] = $row;
			$event->setLink($this->tablesUrl('/table/' . $subjectParams['table']['id'] . '/row/' . $event->getObjectId()));

			if ($event->getSubject() === ActivityManager::SUBJECT_ROW_UPDATE) {
				foreach ($subjectParams['changeCols'] as $changeCol) {
					$params['col-' . $changeCol['id']] = [
						'type' => 'highlight',
						'id' => (string)$changeCol['id'],
						'name' => $changeCol['name'] ?? '',
					];
				}
			}
		}

		if (array_key_exists('before', $subjectParams) && is_string($subjectParams['before'])) {
			$params['before'] = [
				'type' => 'highlight',
				'id' => $subjectParams['before'],
				'name' => $subjectParams['before'] ?? ''
			];
		}

		if (array_key_exists('after', $subjectParams)) {
			$params['after'] = [
				'type' => 'highlight',
				'id' => (string)$subjectParams['after'],
				'name' => $subjectParams['after'] ?? ''
			];
		}

		try {
			$subject = $this->activityManager->getActivityFormat($language, $subjectIdentifier, $subjectParams, $ownActivity);
			$this->setSubjects($event, $subject, $params);
		} catch (\Exception $e) {
		}

		return $event;
	}

	private function setIcon(IEvent $event) {
		$event->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('tables', 'app-dark.svg')));

		if (str_contains($event->getSubject(), '_update')) {
			$event->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('files', 'change.svg')));
		}

		if (str_contains($event->getSubject(), '_create')) {
			$event->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('files', 'add-color.svg')));
		}

		if (str_contains($event->getSubject(), '_delete')) {
			$event->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('files', 'delete-color.svg')));
		}

		return $event;
	}

	private function tablesUrl(string $endpoint) {
		return $this->urlGenerator->linkToRouteAbsolute('tables.page.index') . '#/' . trim($endpoint, '/');
	}

	private function setSubjects(IEvent $event, $subject, array $parameters) {
		$placeholders = $replacements = $richParameters = [];

		foreach ($parameters as $placeholder => $parameter) {
			$placeholders[] = '{' . $placeholder . '}';
			if (is_array($parameter) && array_key_exists('name', $parameter)) {
				$replacements[] = $parameter['name'];
				$richParameters[$placeholder] = $parameter;
			} else {
				$replacements[] = '';
			}
		}

		$event->setParsedSubject(str_replace($placeholders, $replacements, $subject))
			->setRichSubject($subject, $richParameters);
		$event->setSubject($subject, $parameters);
	}
}
