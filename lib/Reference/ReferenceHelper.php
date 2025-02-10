<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Reference;

use Exception;
use OC\Collaboration\Reference\LinkReferenceProvider;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\ViewService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\Reference;
use OCP\IConfig;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;
use Throwable;

class ReferenceHelper {
	protected const RICH_OBJECT_TYPE = Application::APP_ID . '_link';

	protected ?string $userId;
	protected IURLGenerator $urlGenerator;
	protected LinkReferenceProvider $linkReferenceProvider;
	protected ViewService $viewService;
	protected TableService $tableService;
	protected ColumnService $columnService;
	protected RowService $rowService;
	protected IConfig $config;
	protected LoggerInterface $logger;

	public function __construct(IURLGenerator $urlGenerator,
		ViewService $viewService,
		TableService $tableService,
		ColumnService $columnService,
		RowService $rowService,
		LinkReferenceProvider $linkReferenceProvider,
		?string $userId,
		IConfig $config,
		LoggerInterface $logger) {
		$this->userId = $userId;
		$this->urlGenerator = $urlGenerator;
		$this->linkReferenceProvider = $linkReferenceProvider;
		$this->viewService = $viewService;
		$this->tableService = $tableService;
		$this->rowService = $rowService;
		$this->columnService = $columnService;
		$this->config = $config;
		$this->logger = $logger;
	}

	public function matchReference(string $referenceText, ?string $type = null): bool {
		if ($this->userId === null) {
			return false;
		}
		$start = $this->urlGenerator->getAbsoluteURL('/apps/' . Application::APP_ID);
		$startIndex = $this->urlGenerator->getAbsoluteURL('/index.php/apps/' . Application::APP_ID);

		$noIndexMatchTable = false;
		$indexMatchTable = false;
		if ($type === null || $type === 'table') {
			// link example: https://nextcloud.local/apps/tables/#/table/3
			$noIndexMatchTable = preg_match('/^' . preg_quote($start, '/') . '\/#\/table\/\d+$/i', $referenceText) === 1;
			$indexMatchTable = preg_match('/^' . preg_quote($startIndex, '/') . '\/#\/table\/\d+$/i', $referenceText) === 1;
		}

		$noIndexMatchView = false;
		$indexMatchView = false;
		if ($type === null || $type === 'view') {
			// link example: https://nextcloud.local/apps/tables/#/view/3
			$noIndexMatchView = preg_match('/^' . preg_quote($start, '/') . '\/#\/view\/\d+$/i', $referenceText) === 1;
			$indexMatchView = preg_match('/^' . preg_quote($startIndex, '/') . '\/#\/view\/\d+$/i', $referenceText) === 1;
		}

		return $noIndexMatchTable || $indexMatchTable || $noIndexMatchView || $indexMatchView;
	}

	/** @psalm-suppress InvalidReturnType */
	public function resolveReference(string $referenceText): ?IReference {
		if ($this->matchReference($referenceText)) {
			if ($this->matchReference($referenceText, 'table')) {
				$elementId = $this->getTableIdFromLink($referenceText);
			} elseif ($this->matchReference($referenceText, 'view')) {
				$elementId = $this->getViewIdFromLink($referenceText);
			}

			if (!isset($elementId) || $this->userId === null) {
				// fallback to opengraph if it matches, but somehow we can't resolve
				/** @psalm-suppress InvalidReturnStatement */
				return $this->linkReferenceProvider->resolveReference($referenceText);
			}
			try {
				if ($this->matchReference($referenceText, 'table')) {
					$element = $this->tableService->find($elementId, false, $this->userId);
				} elseif ($this->matchReference($referenceText, 'view')) {
					$element = $this->viewService->find($elementId, false, $this->userId);
				} else {
					$e = new Exception('Neither table nor view is given.');
					$this->logger->error($e->getMessage(), ['exception' => $e]);
					throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
				}
			} catch (Exception|Throwable $e) {
				/** @psalm-suppress InvalidReturnStatement */
				return $this->linkReferenceProvider->resolveReference($referenceText);
			}

			$reference = new Reference($referenceText);
			$referenceInfo = [];

			if ($element->getEmoji()) {
				$reference->setDescription($element->getEmoji() . ' ' . $element->getTitle());
				$referenceInfo['title'] = $element->getTitle();
				$referenceInfo['emoji'] = $element->getEmoji();
			} else {
				$reference->setTitle($element->getTitle());
				$referenceInfo['title'] = $element->getTitle();
			}

			$reference->setDescription($element->getOwnerDisplayName() ? $element->getOwnerDisplayName() : $element->getOwnership());

			$referenceInfo['ownership'] = $element->getOwnership();
			$referenceInfo['ownerDisplayName'] = $element->getOwnerDisplayName();
			$referenceInfo['rowsCount'] = $element->getRowsCount();

			$imageUrl = $this->urlGenerator->getAbsoluteURL(
				$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
			);
			$reference->setImageUrl($imageUrl);

			$referenceInfo['link'] = $referenceText;
			$reference->setUrl($referenceText);

			// add rows data
			try {
				if ($this->matchReference($referenceText, 'table')) {
					$referenceInfo['rows'] = $this->rowService->findAllByTable($elementId, $this->userId, 10, 0);
				} elseif ($this->matchReference($referenceText, 'view')) {
					$referenceInfo['rows'] = $this->rowService->findAllByView($elementId, $this->userId, 10, 0);
				}
			} catch (InternalError|PermissionError|DoesNotExistException|MultipleObjectsReturnedException $e) {
				// TODO add logging
			}

			// set referenceType to { table, view }
			if ($this->matchReference($referenceText, 'table')) {
				$referenceInfo['type'] = 'table';
			} elseif ($this->matchReference($referenceText, 'view')) {
				$referenceInfo['type'] = 'view';
			}

			$reference->setRichObject(
				$this::RICH_OBJECT_TYPE,
				$referenceInfo,
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

		preg_match('/^' . preg_quote($start, '/') . '\/#\/table\/(\d+)(?:\/[^\/]+)*$/i', $url, $matches);
		if (!$matches || count($matches) < 2) {
			preg_match('/^' . preg_quote($startIndex, '/') . '\/#\/table\/(\d+)(?:\/[^\/]+)*$/i', $url, $matches);
		}
		if ($matches && count($matches) > 1) {
			return (int)$matches[1];
		}

		return null;
	}

	/**
	 * @param string $url
	 * @return int|null
	 */
	public function getViewIdFromLink(string $url): ?int {
		$start = $this->urlGenerator->getAbsoluteURL('/apps/' . Application::APP_ID);
		$startIndex = $this->urlGenerator->getAbsoluteURL('/index.php/apps/' . Application::APP_ID);

		preg_match('/^' . preg_quote($start, '/') . '\/#\/view\/(\d+)(?:\/[^\/]+)*$/i', $url, $matches);
		if (!$matches || count($matches) < 2) {
			preg_match('/^' . preg_quote($startIndex, '/') . '\/#\/view\/(\d+)(?:\/[^\/]+)*$/i', $url, $matches);
		}
		if ($matches && count($matches) > 1) {
			return (int)$matches[1];
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
