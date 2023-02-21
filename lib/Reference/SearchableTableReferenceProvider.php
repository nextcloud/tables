<?php

namespace OCA\Tables\Reference;

use Exception;
use OC\Collaboration\Reference\LinkReferenceProvider;
use OCA\Tables\Service\TableService;
use OCP\Collaboration\Reference\ADiscoverableReferenceProvider;
use OCP\Collaboration\Reference\ISearchableReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OC\Collaboration\Reference\ReferenceManager;
use OCA\Tables\AppInfo\Application;
use OCP\Collaboration\Reference\IReference;
use OCP\IL10N;
use OCP\IURLGenerator;
use Throwable;

class SearchableTableReferenceProvider extends ADiscoverableReferenceProvider implements ISearchableReferenceProvider {
	private const RICH_OBJECT_TYPE = Application::APP_ID . '_table';

	private ?string $userId;
	private ReferenceManager $referenceManager;
	private IL10N $l10n;
	private IURLGenerator $urlGenerator;
	private LinkReferenceProvider $linkReferenceProvider;
	private TableService $tableService;

	public function __construct(IL10N $l10n,
								IURLGenerator $urlGenerator,
								TableService $tableService,
								ReferenceManager $referenceManager,
								LinkReferenceProvider $linkReferenceProvider,
								?string $userId) {
		$this->userId = $userId;
		$this->referenceManager = $referenceManager;
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
		$this->linkReferenceProvider = $linkReferenceProvider;
		$this->tableService = $tableService;
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
		return $this->l10n->t('Tables tables');
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

	/**
	 * @inheritDoc
	 */
	public function resolveReference(string $referenceText): ?IReference {
		if ($this->matchReference($referenceText)) {
			$tableId = $this->getTableIdFromLink($referenceText);
			if ($tableId === null) {
				// fallback to opengraph if it matches but somehow we can't resolve
				return $this->linkReferenceProvider->resolveReference($referenceText);
			}
			try {
				$table = $this->tableService->find($tableId, $this->userId)->jsonSerialize();
			} catch (Exception | Throwable $e) {
				return $this->linkReferenceProvider->resolveReference($referenceText);
			}

			$tableReferenceInfo = [...$table];

			$reference = new Reference($referenceText);
			$tableEmoji = $table['emoji'];
			$refTitle = $tableEmoji ? $tableEmoji . ' ' . $table['title'] : $table['title'];
			$reference->setTitle($refTitle);

			$reference->setDescription($table['createdBy']);
			$tableReferenceInfo['description'] = $table['createdBy'];

			$imageUrl = $this->urlGenerator->getAbsoluteURL(
				$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
			);
			$reference->setImageUrl($imageUrl);

			$tableReferenceInfo['link'] = $referenceText;
			$reference->setUrl($referenceText);

			$reference->setRichObject(
				self::RICH_OBJECT_TYPE,
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

	/**
	 * @inheritDoc
	 */
	public function getCachePrefix(string $referenceId): string {
		return $this->userId ?? '';
	}

	/**
	 * @inheritDoc
	 */
	public function getCacheKey(string $referenceId): ?string {
		return $referenceId;
	}

	/**
	 * @param string $userId
	 * @return void
	 */
	public function invalidateUserCache(string $userId): void {
		$this->referenceManager->invalidateCache($userId);
	}
}
