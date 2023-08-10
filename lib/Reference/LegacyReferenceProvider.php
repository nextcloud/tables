<?php

namespace OCA\Tables\Reference;

use OC\Collaboration\Reference\ReferenceManager;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\IReferenceProvider;
use OCP\IL10N;
use OCP\IURLGenerator;

class LegacyReferenceProvider implements IReferenceProvider {
	private ReferenceHelper $referenceHelper;
	private ReferenceManager $referenceManager;
	private IURLGenerator $urlGenerator;
	private IL10N $l10n;

	public function __construct(IL10N $l10n, IURLGenerator $urlGenerator, ReferenceHelper $referenceHelper, ReferenceManager $referenceManager) {
		$this->referenceHelper = $referenceHelper;
		$this->referenceManager = $referenceManager;
		$this->urlGenerator = $urlGenerator;
		$this->l10n = $l10n;
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
		$this->referenceManager->invalidateCache($userId);
	}
}
