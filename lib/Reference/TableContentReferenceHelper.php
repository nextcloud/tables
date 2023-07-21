<?php

namespace OCA\Tables\Reference;

use Exception;
use OC\Collaboration\Reference\LinkReferenceProvider;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\ViewService;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\Reference;
use OCP\IConfig;
use OCP\IURLGenerator;
use Throwable;

class TableContentReferenceHelper extends ReferenceHelper {
	protected const RICH_OBJECT_TYPE = Application::APP_ID . '_content';

	public function __construct(IURLGenerator $urlGenerator,
		ViewService $viewService,
		ColumnService $columnService,
		RowService $rowService,
		LinkReferenceProvider $linkReferenceProvider,
		?string $userId,
		IConfig $config) {
		parent::__construct($urlGenerator, $viewService, $columnService, $rowService, $linkReferenceProvider, $userId, $config);
	}

	public function matchReference(string $referenceText): bool {
		if ($this->userId === null) {
			return false;
		}
		$start = $this->urlGenerator->getAbsoluteURL('/apps/' . Application::APP_ID);
		$startIndex = $this->urlGenerator->getAbsoluteURL('/index.php/apps/' . Application::APP_ID);

		// link example:
		// https://nextcloud.local/apps/tables/#/table/3
		$noIndexMatch = preg_match('/^' . preg_quote($start, '/') . '\/#\/view\/\d+\/content$/i', $referenceText) === 1;
		$indexMatch = preg_match('/^' . preg_quote($startIndex, '/') . '\/#\/view\/\d+\/content$/i', $referenceText) === 1;

		return $noIndexMatch || $indexMatch;
	}

	/** @noinspection PhpUndefinedMethodInspection */
	/** @psalm-suppress InvalidReturnType */
	public function resolveReference(string $referenceText): ?IReference {
		if ($this->matchReference($referenceText)) {
			$viewId = $this->getTableIdFromLink($referenceText);
			if ($viewId === null || $this->userId === null) {
				// fallback to opengraph if it matches, but somehow we can't resolve
				/** @psalm-suppress InvalidReturnStatement */
				return $this->linkReferenceProvider->resolveReference($referenceText);
			}
			try {
				$view = $this->viewService->find($viewId, false, $this->userId);
			} catch (Exception | Throwable $e) {
				/** @psalm-suppress InvalidReturnStatement */
				return $this->linkReferenceProvider->resolveReference($referenceText);
			}

			$reference = new Reference($referenceText);
			$viewReferenceInfo = [];

			if ($view->getEmoji()) {
				$reference->setDescription($view->getEmoji() . ' ' . $view->getTitle());
				$viewReferenceInfo['title'] = $view->getTitle();
				$viewReferenceInfo['emoji'] = $view->getEmoji();
			} else {
				$reference->setTitle($view->getTitle());
				$viewReferenceInfo['title'] = $view->getTitle();
			}

			$reference->setDescription($view->getOwnerDisplayName() ?? $view->getOwnership());

			$viewReferenceInfo['ownership'] = $view->getOwnership();
			$viewReferenceInfo['ownerDisplayName'] = $view->getOwnerDisplayName();
			$viewReferenceInfo['rowsCount'] = $view->getRowsCount();

			$imageUrl = $this->urlGenerator->getAbsoluteURL(
				$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
			);
			$reference->setImageUrl($imageUrl);

			$viewReferenceInfo['link'] = $referenceText;
			$reference->setUrl($referenceText);


			// add Columns
			$viewReferenceInfo['columns'] = $this->columnService->findAllByView($viewId, $this->userId);
			// add rows data
			try {
				$viewReferenceInfo['rows'] = $this->rowService->findAllByView($viewId, $this->userId,10, 0);
			} catch (InternalError $e) {
			} catch (PermissionError $e) {
			}

			$reference->setRichObject(
				$this::RICH_OBJECT_TYPE,
				$viewReferenceInfo,
			);
			return $reference;
		}

		return null;
	}
}
