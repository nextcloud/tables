<?php

namespace OCA\Tables\Reference;

use Exception;
use OC\Collaboration\Reference\LinkReferenceProvider;
use OCA\Tables\Service\TableService;
use OCP\Collaboration\Reference\IReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OC\Collaboration\Reference\ReferenceManager;
use OCA\Tables\AppInfo\Application;
use OCP\Collaboration\Reference\IReference;
use OCP\IURLGenerator;
use Throwable;

class ReferenceHelper {
	private const RICH_OBJECT_TYPE = Application::APP_ID . '_table';

	private ?string $userId;
	private ReferenceManager $referenceManager;
	private IURLGenerator $urlGenerator;
	private LinkReferenceProvider $linkReferenceProvider;
	private TableService $tableService;

	public function __construct(IURLGenerator $urlGenerator,
								TableService $tableService,
								ReferenceManager $referenceManager,
								LinkReferenceProvider $linkReferenceProvider,
								?string $userId) {
		$this->userId = $userId;
		$this->referenceManager = $referenceManager;
		$this->urlGenerator = $urlGenerator;
		$this->linkReferenceProvider = $linkReferenceProvider;
		$this->tableService = $tableService;
	}

	public function matchReference(string $referenceText): bool {
		if ($this->userId === null) {
			return false;
		}
		$start = $this->urlGenerator->getAbsoluteURL('/apps/' . Application::APP_ID);
		$startIndex = $this->urlGenerator->getAbsoluteURL('/index.php/apps/' . Application::APP_ID);

		// link example:
		// https://nextcloud.local/apps/tables/#/table/3
		$noIndexMatch = preg_match('/^' . preg_quote($start, '/') . '\/#\/table\/\d+$/i', $referenceText) === 1;
		$indexMatch = preg_match('/^' . preg_quote($startIndex, '/') . '\/#\/table\/\d+$/i', $referenceText) === 1;

		return $noIndexMatch || $indexMatch;
	}

	/** @noinspection PhpUndefinedMethodInspection */
	public function resolveReference(string $referenceText): ?IReference {
		if ($this->matchReference($referenceText)) {
			$tableId = $this->getTableIdFromLink($referenceText);
			if ($tableId === null || $this->userId === null) {
				// fallback to opengraph if it matches, but somehow we can't resolve
				return $this->linkReferenceProvider->resolveReference($referenceText);
			}
			try {
				$table = $this->tableService->find($tableId, $this->userId);
			} catch (Exception | Throwable $e) {
				return $this->linkReferenceProvider->resolveReference($referenceText);
			}

			$reference = new Reference($referenceText);

			if ($table->getEmoji()) {
				$reference->setDescription($table->getEmoji() . ' ' . $table->getTitle());
				$tableReferenceInfo['title'] = $table->getTitle();
				$tableReferenceInfo['emoji'] = $table->getEmoji();
			} else {
				$reference->setTitle($table->getTitle());
				$tableReferenceInfo['title'] = $table->getTitle();
			}

			$reference->setDescription($table->getOwnerDisplayName() ?? $table->getOwnership());

			$tableReferenceInfo['ownership'] = $table->getOwnership();
			$tableReferenceInfo['ownerDisplayName'] = $table->getOwnerDisplayName();
			$tableReferenceInfo['rowsCount'] = $table->getRowsCount();

			$imageUrl = $this->urlGenerator->getAbsoluteURL(
				$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
			);
			$reference->setImageUrl($imageUrl);

			$tableReferenceInfo['link'] = $referenceText;
			$reference->setUrl($referenceText);

			$reference->setRichObject(
				$this::RICH_OBJECT_TYPE,
				$tableReferenceInfo,
			);
			return $reference;
		}

		return null;
	}

	/**
	 * @param string $url
	 * @return int|null
	 */
	public function getTableIdFromLink(string $url): ?int {
		$start = $this->urlGenerator->getAbsoluteURL('/apps/' . Application::APP_ID);
		$startIndex = $this->urlGenerator->getAbsoluteURL('/index.php/apps/' . Application::APP_ID);

		preg_match('/^' . preg_quote($start, '/') . '\/#\/table\/(\d+)$/i', $url, $matches);
		if (!$matches || count($matches) < 2) {
			preg_match('/^' . preg_quote($startIndex, '/') . '\/#\/table\/(\d+)$/i', $url, $matches);
		}
		if ($matches && count($matches) > 1) {
			return (int) $matches[1];
		}

		return null;
	}

	public function getCachePrefix(string $referenceId): string {
		return $this->userId ?? '';
	}

	public function getCacheKey(string $referenceId): ?string {
		// disable caching for development
		return '-';
		// return $referenceId;
	}

}
