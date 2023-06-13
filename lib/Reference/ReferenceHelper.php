<?php

namespace OCA\Tables\Reference;

use Exception;
use OC\Collaboration\Reference\LinkReferenceProvider;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\TableService;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\Reference;
use OCP\IConfig;
use OCP\IURLGenerator;
use Throwable;

class ReferenceHelper {
	private const RICH_OBJECT_TYPE = Application::APP_ID . '_table';

	private ?string $userId;
	private IURLGenerator $urlGenerator;
	private LinkReferenceProvider $linkReferenceProvider;
	private TableService $tableService;
	private RowService $rowService;

	private IConfig $config;

	public function __construct(IURLGenerator $urlGenerator,
		TableService $tableService,
		RowService $rowService,
		LinkReferenceProvider $linkReferenceProvider,
		?string $userId,
		IConfig $config) {
		$this->userId = $userId;
		$this->urlGenerator = $urlGenerator;
		$this->linkReferenceProvider = $linkReferenceProvider;
		$this->tableService = $tableService;
		$this->rowService = $rowService;
		$this->config = $config;
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
	/** @psalm-suppress InvalidReturnType */
	public function resolveReference(string $referenceText): ?IReference {
		if ($this->matchReference($referenceText)) {
			$tableId = $this->getTableIdFromLink($referenceText);
			if ($tableId === null || $this->userId === null) {
				// fallback to opengraph if it matches, but somehow we can't resolve
				/** @psalm-suppress InvalidReturnStatement */
				return $this->linkReferenceProvider->resolveReference($referenceText);
			}
			try {
				$table = $this->tableService->find($tableId, $this->userId);
			} catch (Exception | Throwable $e) {
				/** @psalm-suppress InvalidReturnStatement */
				return $this->linkReferenceProvider->resolveReference($referenceText);
			}

			$reference = new Reference($referenceText);
			$tableReferenceInfo = [];

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

			// add rows data
			try {
				$tableReferenceInfo['rows'] = $this->rowService->findAllByTable($tableId, 10, 0);
			} catch (InternalError $e) {
			} catch (PermissionError $e) {
			}

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
		if ($this->config->getSystemValue('debug')) {
			return $this->randomString(10);
		} else {
			return $referenceId;
		}
	}

	private function randomString(int $length): string {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$randString = '';
		for ($i = 0; $i < $length; $i++) {
			$randString = $characters[rand(0, strlen($characters))];
		}
		return $randString;
	}
}
