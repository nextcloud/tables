<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Notification;

use OCA\Tables\Activity\ActivityManager;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Service\PermissionsService;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

class Notifier implements INotifier {
	public function __construct(
		protected readonly IFactory $l10nFactory,
		protected readonly IURLGenerator $url,
		protected readonly IUserManager $userManager,
		protected readonly PermissionsService $permissionsService,
	) {
	}

	/**
	 * Identifier of the notifier, only use [a-z0-9_]
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getID(): string {
		return 'tables';
	}

	/**
	 * Human readable name describing the notifier
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getName(): string {
		return $this->l10nFactory->get('tables')->t('Tables');
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws \InvalidArgumentException When the notification was not prepared by a notifier
	 * @since 9.0.0
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== 'tables' || $notification->getObjectType() === 'activity_notification') {
			throw new UnknownNotificationException();
		}

		$l = $this->l10nFactory->get('tables', $languageCode);
		$notification->setIcon($this->url->getAbsoluteURL($this->url->imagePath('tables', 'app-dark.svg')));
		$params = $notification->getSubjectParameters();
		$hasViewContext = ($params['isViewContext'] ?? false) === true;
		$parsedSubject = '';
		$subject = $notification->getSubject();
		$richParams = [];
		$link = '';

		if (isset($params['author'])) {
			$authorId = (string)$params['author'];
			$authorUser = $authorId !== '' ? $this->userManager->get($authorId) : null;
			$authorName = $authorUser?->getDisplayName() ?? $authorId;
			$richParams['user'] = [
				'type' => 'user',
				'id' => $authorId,
				'name' => $authorName,
			];
		}

		if (isset($params['table']['id'], $params['table']['title'])) {
			if (!$this->permissionsService->canAccessNodeById(Application::NODE_TYPE_TABLE, $params['table']['id'])) {
				throw new UnknownNotificationException();
			}

			$richParams['table'] = [
				'type' => 'highlight',
				'id' => (string)$params['table']['id'],
				'name' => (string)$params['table']['title'],
				'link' => $this->tablesUrl('/table/' . $params['table']['id']),
			];
		}

		if ($hasViewContext && isset($params['view']['id'], $params['view']['title'])) {
			if (!$this->permissionsService->canAccessNodeById(Application::NODE_TYPE_VIEW, $params['view']['id'])) {
				throw new UnknownNotificationException();
			}

			$richParams['view'] = [
				'type' => 'highlight',
				'id' => (string)$params['view']['id'],
				'name' => (string)$params['view']['title'],
				'link' => $this->tablesUrl('/view/' . $params['view']['id']),
			];
		}

		if (isset($params['row']['id'])) {
			$richParams['row'] = [
				'type' => 'highlight',
				'id' => (string)$params['row']['id'],
				'name' => 'row',
				'link' => $hasViewContext && isset($params['view']['id'])
					? $this->tablesUrl('/view/' . $params['view']['id'] . '/row/' . $params['row']['id'])
					: $this->tablesUrl('/table/' . $params['table']['id'] . '/row/' . $params['row']['id']),
			];
		}

		if (isset($params['column']['id'])) {
			$columnTitle = $params['column']['title'] ?? $params['column']['name'] ?? ('#' . $params['column']['id']);
			$richParams['column'] = [
				'type' => 'highlight',
				'id' => (string)$params['column']['id'],
				'name' => (string)$columnTitle,
			];
		}

		if (isset($params['group']['id'])) {
			$richParams['group'] = [
				'type' => 'highlight',
				'id' => (string)$params['group']['id'],
				'name' => (string)($params['group']['name'] ?? $params['group']['id']),
			];
		}

		if (isset($params['team']['id'])) {
			$richParams['team'] = [
				'type' => 'highlight',
				'id' => (string)$params['team']['id'],
				'name' => (string)($params['team']['name'] ?? $params['team']['id']),
			];
		}

		switch ($notification->getSubject()) {
			case ActivityManager::SUBJECT_ROW_CREATE:
				$link = $richParams['row']['link'];
				$parsedSubject = $hasViewContext
					? $l->t('A new table row has been created in view %2$s by %1$s.', [
						$richParams['user']['name'] ?? '',
						$richParams['view']['name'] ?? '',
					])
					: $l->t('A new table row has been created in table %2$s by %1$s.', [
						$richParams['user']['name'] ?? '',
						$richParams['table']['name'] ?? '',
					]);
				$subject = $hasViewContext
					? $l->t('{user} has created a new {row} in view {view}')
					: $l->t('{user} has created a new {row} in table {table}');
				break;

			case ActivityManager::SUBJECT_ROW_UPDATE:
				$link = $richParams['row']['link'];
				$parsedSubject = $hasViewContext
					? $l->t('A table row has been updated in view %2$s by %1$s.', [
						$richParams['user']['name'] ?? '',
						$richParams['view']['name'] ?? '',
					])
					: $l->t('A table row has been updated in table %2$s by %1$s.', [
						$richParams['user']['name'] ?? '',
						$richParams['table']['name'] ?? '',
					]);
				$subject = $hasViewContext
					? $l->t('{user} has updated this {row} in view {view}')
					: $l->t('{user} has updated this {row} in table {table}');
				break;

			case ActivityManager::SUBJECT_ROW_DELETE:
				$link = $richParams['row']['link'];
				$parsedSubject = $hasViewContext
					? $l->t('A table row has been deleted from view %2$s by %1$s.', [
						$richParams['user']['name'] ?? '',
						$richParams['view']['name'] ?? '',
					])
					: $l->t('A table row has been deleted from table %2$s by %1$s.', [
						$richParams['user']['name'] ?? '',
						$richParams['table']['name'] ?? '',
					]);
				$subject = $hasViewContext
					? $l->t('{user} has deleted this {row} in view {view}')
					: $l->t('{user} has deleted this {row} in table {table}');
				break;

			case ActivityManager::SUBJECT_ROW_ASSIGN:
				$link = $richParams['row']['link'];
				$assignedTargetType = (string)($params['assignedTargetType'] ?? 'user');
				if ($assignedTargetType === 'group') {
					$parsedSubject = $hasViewContext
						? $l->t('Group %2$s has been assigned in row %3$s of view %4$s by %1$s.', [
							$richParams['user']['name'] ?? '',
							$richParams['group']['name'] ?? '',
							$richParams['row']['name'] ?? '',
							$richParams['view']['name'] ?? '',
						])
						: $l->t('Group %2$s has been assigned in row %3$s of table %4$s by %1$s.', [
							$richParams['user']['name'] ?? '',
							$richParams['group']['name'] ?? '',
							$richParams['row']['name'] ?? '',
							$richParams['table']['name'] ?? '',
						]);
					$subject = $hasViewContext
						? $l->t('{user} has assigned group {group} in {row} in view {view}')
						: $l->t('{user} has assigned group {group} in {row} in table {table}');
				} elseif ($assignedTargetType === 'team') {
					$parsedSubject = $hasViewContext
						? $l->t('Team %2$s has been assigned in row %3$s of view %4$s by %1$s.', [
							$richParams['user']['name'] ?? '',
							$richParams['team']['name'] ?? '',
							$richParams['row']['name'] ?? '',
							$richParams['view']['name'] ?? '',
						])
						: $l->t('Team %2$s has been assigned in row %3$s of table %4$s by %1$s.', [
							$richParams['user']['name'] ?? '',
							$richParams['team']['name'] ?? '',
							$richParams['row']['name'] ?? '',
							$richParams['table']['name'] ?? '',
						]);
					$subject = $hasViewContext
						? $l->t('{user} has assigned team {team} in {row} in view {view}')
						: $l->t('{user} has assigned team {team} in {row} in table {table}');
				} else {
					$parsedSubject = $hasViewContext
						? $l->t('You have been assigned in row %2$s of view %3$s by %1$s.', [
							$richParams['user']['name'] ?? '',
							$richParams['row']['name'] ?? '',
							$richParams['view']['name'] ?? '',
						])
						: $l->t('You have been assigned in row %2$s of table %3$s by %1$s.', [
							$richParams['user']['name'] ?? '',
							$richParams['row']['name'] ?? '',
							$richParams['table']['name'] ?? '',
						]);
					$subject = $hasViewContext
						? $l->t('{user} has assigned you in {row} in view {view}')
						: $l->t('{user} has assigned you in {row} in table {table}');
				}
				break;

			case ActivityManager::SUBJECT_COLUMN_CREATE:
				$link = $hasViewContext ? $richParams['view']['link'] : $richParams['table']['link'];
				$parsedSubject = $hasViewContext
					? $l->t('A new column has been created in view %2$s by %1$s.', [
						$richParams['user']['name'] ?? '',
						$richParams['view']['name'] ?? '',
					])
					: $l->t('A new column has been created in table %2$s by %1$s.', [
						$richParams['user']['name'] ?? '',
						$richParams['table']['name'] ?? '',
					]);
				$subject = $hasViewContext
					? $l->t('{user} has created a new column {column} in view {view}')
					: $l->t('{user} has created a new column {column} in table {table}');
				break;

			case ActivityManager::SUBJECT_COLUMN_UPDATE:
				$link = $hasViewContext ? $richParams['view']['link'] : $richParams['table']['link'];
				$parsedSubject = $hasViewContext
					? $l->t('A column has been updated in view %2$s by %1$s.', [
						$richParams['user']['name'] ?? '',
						$richParams['view']['name'] ?? '',
					])
					: $l->t('A column has been updated in table %2$s by %1$s.', [
						$richParams['user']['name'] ?? '',
						$richParams['table']['name'] ?? '',
					]);
				$subject = $hasViewContext
					? $l->t('{user} has updated the column {column} in view {view}')
					: $l->t('{user} has updated the column {column} in table {table}');
				break;

			case ActivityManager::SUBJECT_COLUMN_DELETE:
				$link = $hasViewContext ? $richParams['view']['link'] : $richParams['table']['link'];
				$parsedSubject = $hasViewContext
					? $l->t('A column has been deleted from view %2$s by %1$s.', [
						$richParams['user']['name'] ?? '',
						$richParams['view']['name'] ?? '',
					])
					: $l->t('A column has been deleted from table %2$s by %1$s.', [
						$richParams['user']['name'] ?? '',
						$richParams['table']['name'] ?? '',
					]);
				$subject = $hasViewContext
					? $l->t('{user} has deleted the column {column} from view {view}')
					: $l->t('{user} has deleted the column {column} from table {table}');
				break;

			case ActivityManager::SUBJECT_IMPORT_FINISHED:
				$link = $richParams['table']['link'];
				$recipient = $notification->getUser();
				$isActivityOwner = $params['author'] === $recipient;
				$parsedSubject = $isActivityOwner
					? $l->t('You have imported file to table {table}', [
						$richParams['table']['name'] ?? '',
					])
					: $l->t('{user} has imported file to table {table}', [
						$richParams['user']['name'] ?? '',
						$richParams['table']['name'] ?? '',
					]);
				$subject = $isActivityOwner
					? $l->t('You have imported file to table {table}')
					: $l->t('{user} has imported file to table {table}');
				break;

			default:
				throw new UnknownNotificationException();
		}

		$notification->setParsedSubject($parsedSubject)
			->setRichSubject($subject, $richParams)
			->setLink($link);

		return $notification;
	}

	private function tablesUrl(string $endpoint): string {
		return $this->url->linkToRouteAbsolute('tables.page.index') . '#/' . trim($endpoint, '/');
	}
}
