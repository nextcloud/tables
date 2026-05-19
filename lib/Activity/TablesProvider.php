<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Activity;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Service\PermissionsService;
use OCP\Activity\Exceptions\UnknownActivityException;
use OCP\Activity\IEvent;
use OCP\Activity\IProvider;
use OCP\IURLGenerator;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class TablesProvider implements IProvider {

	public function __construct(
		private $userId,
		private IURLGenerator $urlGenerator,
		private ActivityManager $activityManager,
		private IUserManager $userManager,
		private LoggerInterface $logger,
		private PermissionsService $permissionsService,
	) {
	}

	/**
	 * @throws UnknownActivityException
	 */
	public function parse($language, IEvent $event, ?IEvent $previousEvent = null): IEvent {
		if ($event->getApp() !== 'tables') {
			throw new UnknownActivityException();
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
			$subjectParameters = [
				'user' => [
					'type' => 'user',
					'id' => $author,
					'name' => $user->getDisplayName()
				],
			];
			$event->setAuthor($author);
		} else {
			$subjectParameters = [
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
			$subjectParameters['table'] = $table;
			$event->setLink($this->tablesUrl('/table/' . $event->getObjectId()));
		}

		if ($event->getObjectType() === ActivityManager::TABLES_OBJECT_VIEW) {
			if (isset($subjectParams['table']['id'], $subjectParams['table']['title'])) {
				$subjectParameters['table'] = [
					'type' => 'highlight',
					'id' => (string)$subjectParams['table']['id'],
					'name' => (string)$subjectParams['table']['title'],
					'link' => $this->tablesUrl('/table/' . $subjectParams['table']['id']),
				];
			}

			$subjectParameters['view'] = [
				'type' => 'highlight',
				'id' => (string)$event->getObjectId(),
				'name' => $event->getObjectName(),
				'link' => $this->tablesUrl('/view/' . $event->getObjectId()),
			];
			$event->setLink($this->tablesUrl('/view/' . $event->getObjectId()));
		}

		if ($event->getObjectType() === ActivityManager::TABLES_OBJECT_ROW) {
			$subjectParameters['table'] = [
				'type' => 'highlight',
				'id' => (string)$subjectParams['table']['id'],
				'name' => (string)$subjectParams['table']['title'],
				'link' => $this->tablesUrl('/table/' . $subjectParams['table']['id']),
			];

			$rowLink = $this->tablesUrl('/table/' . $subjectParams['table']['id'] . '/row/' . $event->getObjectId());
			$isViewContext = $subjectParams['isViewContext'] ?? false;

			if ($isViewContext && isset($subjectParams['view']['id'], $subjectParams['view']['title'])) {
				$subjectParameters['view'] = [
					'type' => 'highlight',
					'id' => (string)$subjectParams['view']['id'],
					'name' => (string)$subjectParams['view']['title'],
					'link' => $this->tablesUrl('/view/' . $subjectParams['view']['id']),
				];
				$rowLink = $this->tablesUrl('/view/' . $subjectParams['view']['id'] . '/row/' . $event->getObjectId());
			}

			$row = [
				'type' => 'highlight',
				'id' => (string)$event->getObjectId(),
				'name' => '#' . $event->getObjectId(),
				'link' => $rowLink,
			];
			$subjectParameters['row'] = $row;
			$event->setLink($rowLink);

			if ($event->getSubject() === ActivityManager::SUBJECT_ROW_UPDATE) {
				foreach ($subjectParams['changeCols'] as $changeCol) {
					$subjectParameters['col-' . $changeCol['id']] = [
						'type' => 'highlight',
						'id' => (string)$changeCol['id'],
						'name' => $changeCol['name'] ?? '',
					];
				}
			}
		}

		if ($event->getObjectType() === ActivityManager::TABLES_OBJECT_COLUMN) {
			$isViewContext = $subjectParams['isViewContext'] ?? false;

			if (isset($subjectParams['table']['id'], $subjectParams['table']['title'])) {
				$subjectParameters['table'] = [
					'type' => 'highlight',
					'id' => (string)$subjectParams['table']['id'],
					'name' => (string)$subjectParams['table']['title'],
					'link' => $this->tablesUrl('/table/' . $subjectParams['table']['id']),
				];
			}

			$subjectParameters['column'] = [
				'type' => 'highlight',
				'id' => (string)$event->getObjectId(),
				'name' => $event->getObjectName(),
			];

			if ($isViewContext && isset($subjectParams['view']['id'], $subjectParams['view']['title'])) {
				$subjectParameters['view'] = [
					'type' => 'highlight',
					'id' => (string)$subjectParams['view']['id'],
					'name' => (string)$subjectParams['view']['title'],
					'link' => $this->tablesUrl('/view/' . $subjectParams['view']['id']),
				];
				$event->setLink($this->tablesUrl('/view/' . $subjectParams['view']['id']));
			} elseif (isset($subjectParams['table']['id'])) {
				$event->setLink($this->tablesUrl('/table/' . $subjectParams['table']['id']));
			}
		}

		if (isset($subjectParams['sharedWith']) && is_array($subjectParams['sharedWith'])) {
			$sharedWith = $subjectParams['sharedWith'];
			$subjectParameters['sharedWith'] = [
				'type' => 'highlight',
				'id' => isset($sharedWith['id']) ? (string)$sharedWith['id'] : 'unknown',
				'name' => isset($sharedWith['name']) ? (string)$sharedWith['name'] : 'unknown',
			];
		}

		if (!isset($subjectParameters['sharedWith'])) {
			if (isset($subjectParameters['table']['id'])
				&& !$this->permissionsService->canAccessNodeById(Application::NODE_TYPE_TABLE, (int)$subjectParameters['table']['id'])) {
				throw new UnknownActivityException();
			}
			if (isset($subjectParameters['view']['id'])
				&& !$this->permissionsService->canAccessNodeById(Application::NODE_TYPE_VIEW, (int)$subjectParameters['view']['id'])) {
				throw new UnknownActivityException();
			}
		}

		if (array_key_exists('before', $subjectParams) && is_string($subjectParams['before'])) {
			$subjectParameters['before'] = [
				'type' => 'highlight',
				'id' => $subjectParams['before'],
				'name' => $subjectParams['before'] ?? ''
			];
		}

		if (array_key_exists('after', $subjectParams)) {
			$subjectParameters['after'] = [
				'type' => 'highlight',
				'id' => (string)$subjectParams['after'],
				'name' => $subjectParams['after'] ?? ''
			];
		}

		$messageParameters = [];
		if ($event->getSubject() === ActivityManager::SUBJECT_IMPORT_FINISHED) {
			$messageParameters['{foundColumnsCount}'] = $subjectParams['importStats']['foundColumnsCount'];
			$messageParameters['{matchingColumnsCount}'] = $subjectParams['importStats']['matchingColumnsCount'];
			$messageParameters['{createdColumnsCount}'] = $subjectParams['importStats']['createdColumnsCount'];
			$messageParameters['{insertedRowsCount}'] = $subjectParams['importStats']['insertedRowsCount'];
			$messageParameters['{updatedRowsCount}'] = $subjectParams['importStats']['updatedRowsCount'];
			$messageParameters['{errorsParsingCount}'] = $subjectParams['importStats']['errorsParsingCount'];
			$messageParameters['{errorsCount}'] = $subjectParams['importStats']['errorsCount'];
		}

		try {
			$subject = $this->activityManager->getActivitySubject($language, $subjectIdentifier, $subjectParams, $ownActivity);
			$message = $this->activityManager->getActivityMessage($language, $subjectIdentifier);
			$this->parseEvent($event, $subject, $subjectParameters, $message, $messageParameters);
		} catch (\Exception $e) {
			$this->logger->warning('Could not parse activity: ' . $e->getMessage(), ['exception' => $e]);
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

		if (str_contains($event->getSubject(), 'share_')) {
			$event->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('core', 'actions/share.svg')));
		}

		return $event;
	}

	private function tablesUrl(string $endpoint) {
		return $this->urlGenerator->linkToRouteAbsolute('tables.page.index') . '#/' . trim($endpoint, '/');
	}

	private function parseEvent(IEvent $event, string $subject, array $subjectParameters, ?string $message, array $messageParameters = []) {
		$placeholders = $replacements = $richParameters = [];

		foreach ($subjectParameters as $placeholder => $parameter) {
			$placeholders[] = '{' . $placeholder . '}';
			if (is_array($parameter) && array_key_exists('name', $parameter)) {
				$replacements[] = $parameter['name'];
				$richParameters[$placeholder] = $parameter;
			} else {
				$replacements[] = '';
			}
		}

		$event->setSubject($subject, $subjectParameters)
			->setParsedSubject(str_replace($placeholders, $replacements, $subject))
			->setRichSubject($subject, $richParameters);

		if ($message) {
			$event->setParsedMessage(strtr($message, $messageParameters));
		}
	}
}
