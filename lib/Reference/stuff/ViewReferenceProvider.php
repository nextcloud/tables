<?php

namespace OCA\Tables\Reference;

use OC\Collaboration\Reference\ReferenceManager;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\IReferenceProvider;

class ViewReferenceProvider implements IReferenceProvider {
	private TableReferenceHelper $referenceHelper;
	private ReferenceManager $referenceManager;

	public function __construct(TableReferenceHelper $referenceHelper, ReferenceManager $referenceManager) {
		$this->referenceHelper = $referenceHelper;
		$this->referenceManager = $referenceManager;
	}

	/**
	 * @inheritDoc
	 */
	public function matchReference(string $referenceText): bool {
		return $this->referenceHelper->matchReference($referenceText, 'view');
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
		return $this->referenceHelper->getViewIdFromLink($url);
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
