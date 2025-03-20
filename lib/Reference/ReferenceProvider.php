<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Reference;

use OC\Collaboration\Reference\ReferenceManager;
use OCA\Tables\AppInfo\Application;
use OCP\Collaboration\Reference\ADiscoverableReferenceProvider;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\ISearchableReferenceProvider;
use OCP\IL10N;
use OCP\IURLGenerator;

class ReferenceProvider extends ADiscoverableReferenceProvider implements ISearchableReferenceProvider {
	private ReferenceManager $referenceManager;
	private IURLGenerator $urlGenerator;
	private IL10N $l10n;

	public function __construct(IL10N $l10n, IURLGenerator $urlGenerator, private ReferenceHelper $referenceHelper, ReferenceManager $referenceManager) {
		$this->referenceManager = $referenceManager;
		$this->urlGenerator = $urlGenerator;
		$this->l10n = $l10n;
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getId(): string {
		return Application::APP_ID . '-ref-tables';
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getTitle(): string {
		return $this->l10n->t('Nextcloud tables');
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getOrder(): int {
		return 10;
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
		);
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getSupportedSearchProviderIds(): array {
		// Not needed as we implement our own picker component
		// return ['tables-search-tables'];
		return [];
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function matchReference(string $referenceText): bool {
		return $this->referenceHelper->matchReference($referenceText);
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function resolveReference(string $referenceText): ?IReference {
		return $this->referenceHelper->resolveReference($referenceText);
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getCachePrefix(string $referenceId): string {
		return $this->referenceHelper->getCachePrefix($referenceId);
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getCacheKey(string $referenceId): ?string {
		return $this->referenceHelper->getCacheKey($referenceId);
	}

	/**
	 * @param string $userId
	 * @return void
	 */
	public function invalidateUserCache(string $userId): void {
		$this->referenceManager->invalidateCache($userId);
	}
}
