<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Listener;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Service\ContextService;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserSession;

/**
 * @template-implements IEventListener<Event|BeforeTemplateRenderedEvent>
 */
class BeforeTemplateRenderedListener implements IEventListener {
	public function __construct(
		protected INavigationManager $navigationManager,
		protected IURLGenerator $urlGenerator,
		protected IUserSession $userSession,
		protected ContextService $contextService,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function handle(Event $event): void {
		if (!$event instanceof BeforeTemplateRenderedEvent) {
			return;
		}

		$user = $this->userSession->getUser();
		if ($user === null) {
			return;
		}

		// temporarily show all
		//$contexts = $this->contextService->findForNavigation($user->getUID());
		$contexts = $this->contextService->findAll($user->getUID());
		foreach ($contexts as $context) {
			/* temporarily, show all
			if ($context->getOwnerType() === Application::OWNER_TYPE_USER
				&& $context->getOwnerId() === $user->getUID()) {


				// filter out entries for owners unless it is set to be visible
				$skipEntry = true;
				foreach ($context->getSharing() as $shareInfo) {
					// TODO: integrate into DB query in Mapper

					if (isset($shareInfo['display_mode']) && $shareInfo['display_mode'] === Application::NAV_ENTRY_MODE_ALL) {
						// a custom override makes it visible
						$skipEntry = false;
						break;
					} elseif (!isset($shareInfo['display_mode']) && $shareInfo['display_mode_default'] === Application::NAV_ENTRY_MODE_ALL) {
						// no custom override, and visible also for owner by default
						$skipEntry = false;
						break;
					}
				}
				if ($skipEntry) {
					continue;
				}
			}
			*/

			$this->navigationManager->add(function () use ($context) {
				$iconRelPath = 'material/' . $context->getIcon() . '.svg';
				if (file_exists(__DIR__ . '/../../img/' . $iconRelPath)) {
					$iconUrl = $this->urlGenerator->imagePath(Application::APP_ID, $iconRelPath);
				} else {
					$iconUrl = $this->urlGenerator->imagePath('core', 'places/default-app-icon.svg');
				}

				$contextUrl = $this->urlGenerator->linkToRoute('tables.page.context', ['contextId' => $context->getId()]);

				return [
					'id' => Application::APP_ID . '_application_' . $context->getId(),
					'name' => $context->getName(),
					'href' => $contextUrl,
					'icon' => $iconUrl,
					'order' => 500,
					'type' => 'link',
				];
			});
		}
	}
}
