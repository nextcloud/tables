<?php

namespace OCA\Tables\Reference;

use Exception;
use OC\Collaboration\Reference\LinkReferenceProvider;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\Table;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
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

class ContentReferenceHelper extends ReferenceHelper {
	protected const RICH_OBJECT_TYPE = Application::APP_ID . '_content';

	public function __construct(IURLGenerator $urlGenerator,
		ViewService $viewService,
		TableService $tableService,
		ColumnService $columnService,
		RowService $rowService,
		LinkReferenceProvider $linkReferenceProvider,
		?string $userId,
		IConfig $config,
		LoggerInterface $logger) {
		parent::__construct($urlGenerator, $viewService, $tableService, $columnService, $rowService, $linkReferenceProvider, $userId, $config, $logger);
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
			$noIndexMatchTable = preg_match('/^' . preg_quote($start, '/') . '\/#\/table\/\d+\/content$/i', $referenceText) === 1;
			$indexMatchTable = preg_match('/^' . preg_quote($startIndex, '/') . '\/#\/table\/\d+\/content$/i', $referenceText) === 1;
		}

		$noIndexMatchView = false;
		$indexMatchView = false;
		if ($type === null || $type === 'view') {
			// link example: https://nextcloud.local/apps/tables/#/view/3
			$noIndexMatchView = preg_match('/^' . preg_quote($start, '/') . '\/#\/view\/\d+\/content$/i', $referenceText) === 1;
			$indexMatchView = preg_match('/^' . preg_quote($startIndex, '/') . '\/#\/view\/\d+\/content$/i', $referenceText) === 1;
		}

		return $noIndexMatchTable || $indexMatchTable || $noIndexMatchView || $indexMatchView;
	}

	/** @psalm-suppress InvalidReturnType
	 * @noinspection DuplicatedCode
	 */
	public function resolveReference(string $referenceText): ?IReference {
		if ($this->matchReference($referenceText)) {
			if($this->matchReference($referenceText, 'table')) {
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
				if($this->matchReference($referenceText, 'table')) {
					$element = $this->tableService->find($elementId, false, $this->userId);
				} elseif ($this->matchReference($referenceText, 'view')) {
					$element = $this->viewService->find($elementId, false, $this->userId);
				} else {
					$e = new Exception('Could not map '.$referenceText.' to any known type.');
					$this->logger->error($e->getMessage(), ['exception' => $e]);
					throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
				}
			} catch (Exception | Throwable $e) {
				/** @psalm-suppress InvalidReturnStatement */
				return $this->linkReferenceProvider->resolveReference($referenceText);
			}

			$reference = new Reference($referenceText);
			$referenceInfo = [];

			$referenceInfo['id'] = $elementId;
			$referenceInfo['type'] = ($element instanceof Table) ? Application::NODE_TYPE_TABLE : Application::NODE_TYPE_VIEW;

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
			$referenceInfo['isShared'] = $element->getIsShared();
			$referenceInfo['onSharePermissions'] = $element->getOnSharePermissions();
			$referenceInfo['rowsCount'] = $element->getRowsCount();

			$imageUrl = $this->urlGenerator->getAbsoluteURL(
				$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
			);
			$reference->setImageUrl($imageUrl);

			$referenceInfo['link'] = $referenceText;
			$reference->setUrl($referenceText);

			// add Columns
			try {
				if($this->matchReference($referenceText, 'table')) {
					$referenceInfo['columns'] = $this->columnService->findAllByTable($elementId);
				} elseif ($this->matchReference($referenceText, 'view')) {
					$referenceInfo['columns'] = $this->columnService->findAllByView($elementId);
				}
			} catch (InternalError|NotFoundError|PermissionError|DoesNotExistException|MultipleObjectsReturnedException $e) {
			}

			// add rows data
			try {
				if($this->matchReference($referenceText, 'table')) {
					$referenceInfo['rows'] = $this->rowService->findAllByTable($elementId, $this->userId, 100, 0);
				} elseif ($this->matchReference($referenceText, 'view')) {
					$referenceInfo['rows'] = $this->rowService->findAllByView($elementId, $this->userId, 100, 0);
				}
			} catch (InternalError|PermissionError|DoesNotExistException|MultipleObjectsReturnedException $e) {
			}

			$reference->setRichObject(
				$this::RICH_OBJECT_TYPE,
				$referenceInfo,
			);
			return $reference;
		}

		return null;
	}
}
