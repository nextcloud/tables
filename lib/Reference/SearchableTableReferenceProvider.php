<?php

namespace OCA\Tables\Reference;

use OCP\Collaboration\Reference\ADiscoverableReferenceProvider;
use OCP\Collaboration\Reference\ISearchableReferenceProvider;
use OCA\Tables\AppInfo\Application;
use OCP\Collaboration\Reference\IReference;
use OCP\IL10N;
use OCP\IURLGenerator;

class SearchableTableReferenceProvider extends ADiscoverableReferenceProvider implements ISearchableReferenceProvider {
	private ReferenceHelper $referenceHelper;
	private IL10N $l10n;
	private IURLGenerator $urlGenerator;

	public function __construct(
		IL10N $l10n,
		IURLGenerator $urlGenerator,
		ReferenceHelper $referenceHelper
	) {
		$this->referenceHelper = $referenceHelper;
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return Application::APP_ID . '-ref-tables';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string {
		return $this->l10n->t('Nextcloud tables');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(): int {
		return 10;
	}

	/**
	 * @inheritDoc
	 */
	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getSupportedSearchProviderIds(): array {
		return ['tables-search-tables'];
	}

	/**
	 * @inheritDoc
	 */
	public function matchReference(string $referenceText): bool {
		return $this->referenceHelper->matchReference($referenceText);
	}

	/**
	 * @inheritDoc
	 */
	public function resolveReference(string $referenceText): ?IReference {
		return $this->referenceHelper->resolveReference($referenceText);
	}

	/**
	 * @param string $url
	 * @return int|null
	 */
	public function getTableIdFromLink(string $url): ?int {
		return $this->referenceHelper->getTableIdFromLink($url);
	}

	/**
	 * @inheritDoc
	 */
	public function getCachePrefix(string $referenceId): string {
		return $this->referenceHelper->getCachePrefix($referenceId);
	}

	/**
	 * @inheritDoc
	 */
	public function getCacheKey(string $referenceId): ?string {
		return $this->referenceHelper->getCacheKey($referenceId);
	}

	/**
	 * @param string $userId
	 * @return void
	 */
	public function invalidateUserCache(string $userId): void {
		$this->referenceHelper->getTableIdFromLink($userId);
	}
}
