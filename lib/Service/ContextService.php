<?php

declare(strict_types=1);

namespace OCA\Tables\Service;

use InvalidArgumentException;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\Context;
use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Db\ContextNodeRelation;
use OCA\Tables\Db\ContextNodeRelationMapper;
use OCA\Tables\Db\Page;
use OCA\Tables\Db\PageContent;
use OCA\Tables\Db\PageContentMapper;
use OCA\Tables\Db\PageMapper;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\PermissionError;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IUserManager;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use Psr\Log\LoggerInterface;

class ContextService {

	private ContextMapper $contextMapper;
	private bool $isCLI;
	private LoggerInterface $logger;
	private ContextNodeRelationMapper $contextNodeRelMapper;
	private PageMapper $pageMapper;
	private PageContentMapper $pageContentMapper;
	private PermissionsService $permissionsService;
	private IUserManager $userManager;
	private IEventDispatcher $eventDispatcher;

	public function __construct(
		ContextMapper             $contextMapper,
		ContextNodeRelationMapper $contextNodeRelationMapper,
		PageMapper                $pageMapper,
		PageContentMapper         $pageContentMapper,
		LoggerInterface           $logger,
		PermissionsService        $permissionsService,
		IUserManager              $userManager,
		IEventDispatcher          $eventDispatcher,
		bool                      $isCLI,
	) {
		$this->contextMapper = $contextMapper;
		$this->isCLI = $isCLI;
		$this->logger = $logger;
		$this->contextNodeRelMapper = $contextNodeRelationMapper;
		$this->pageMapper = $pageMapper;
		$this->pageContentMapper = $pageContentMapper;
		$this->permissionsService = $permissionsService;
		$this->userManager = $userManager;
		$this->eventDispatcher = $eventDispatcher;
	}

	/**
	 * @return Context[]
	 * @throws Exception
	 * @throws InternalError
	 */
	public function findAll(?string $userId): array {
		if ($userId !== null && trim($userId) === '') {
			$userId = null;
		}
		if ($userId === null && !$this->isCLI) {
			$error = 'Try to set no user in context, but request is not allowed.';
			$this->logger->warning($error);
			throw new InternalError($error);
		}
		return $this->contextMapper->findAll($userId);
	}

	/**
	 * @return Context
	 * @throws InternalError
	 */
	public function findById(int $id, ?string $userId): Context {
		if ($userId !== null && trim($userId) === '') {
			$userId = null;
		}
		if ($userId === null && !$this->isCLI) {
			$error = 'Try to set no user in context, but request is not allowed.';
			$this->logger->warning($error);
			throw new InternalError($error);
		}

		return $this->contextMapper->findById($id, $userId);
	}

	/**
	 * @throws Exception
	 */
	public function create(string $name, string $iconName, string $description, array $nodes, string $ownerId, int $ownerType): Context {
		$context = new Context();
		$context->setName(trim($name));
		$context->setIcon(trim($iconName));
		$context->setDescription(trim($description));
		$context->setOwnerId($ownerId);
		$context->setOwnerType($ownerType);

		$this->contextMapper->insert($context);

		if (!empty($nodes)) {
			$context->resetUpdatedFields();
			$this->insertNodesFromArray($context, $nodes);
			$this->insertPage($context);
		}

		return $context;
	}

	/**
	 * @throws Exception
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function update(int $contextId, ?string $name, ?string $iconName, ?string $description): Context {
		$context = $this->contextMapper->findById($contextId);

		if ($name !== null) {
			$context->setName(trim($name));
		}
		if ($iconName !== null) {
			$context->setIcon(trim($iconName));
		}
		if ($description !== null) {
			$context->setDescription(trim($description));
		}

		return $this->contextMapper->update($context);
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws BadRequestError
	 */
	public function transfer(int $contextId, string $newOwnerId, int $newOwnerType): Context {
		$context = $this->contextMapper->findById($contextId);

		// the owner type check can be dropped as soon as NC 29 is the lowest supported version,
		// as the int range as defined in the Controller will be enforced by the Http/Dispatcher.
		if ($newOwnerType !== Application::OWNER_TYPE_USER) {
			throw new BadRequestError('Unsupported owner type');
		}

		if (!$this->userManager->userExists($newOwnerId)) {
			throw new BadRequestError('User does not exist');
		}

		$context->setOwnerId($newOwnerId);
		$context->setOwnerType($newOwnerType);

		$context = $this->contextMapper->update($context);

		$auditEvent = new CriticalActionPerformedEvent(
			sprintf('Tables application with ID %d was transferred to user %s',
				$contextId, $newOwnerId,
			)
		);

		$this->eventDispatcher->dispatchTyped($auditEvent);

		return $context;
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function addNodeToContextById(int $contextId, int $nodeId, int $nodeType, int $permissions, ?string $userId): ContextNodeRelation {
		$context = $this->contextMapper->findById($contextId, $userId);
		return $this->addNodeToContext($context, $nodeId, $nodeType, $permissions);
	}

	/**
	 * @throws Exception
	 */
	public function removeNodeFromContext(ContextNodeRelation $nodeRelation): ContextNodeRelation {
		return $this->contextNodeRelMapper->delete($nodeRelation);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function removeNodeFromContextById(int $nodeRelationId): ContextNodeRelation {
		$nodeRelation = $this->contextNodeRelMapper->findById($nodeRelationId);
		return $this->contextNodeRelMapper->delete($nodeRelation);
	}

	/**
	 * @throws Exception
	 */
	public function addNodeToContext(Context $context, int $nodeId, int $nodeType, int $permissions): ContextNodeRelation {
		$contextNodeRel = new ContextNodeRelation();
		$contextNodeRel->setContextId($context->getId());
		$contextNodeRel->setNodeId($nodeId);
		$contextNodeRel->setNodeType($nodeType);
		$contextNodeRel->setPermissions($permissions);

		return $this->contextNodeRelMapper->insert($contextNodeRel);
	}

	public function addNodeRelToPage(ContextNodeRelation $nodeRel, int $order = null, ?int $pageId = null): PageContent {
		if ($pageId === null) {
			// when no page is given, find the startpage to add it to
			$context = $this->contextMapper->findById($nodeRel->getContextId());
			$pages = $context->getPages();
			foreach ($pages as $page) {
				if ($page['page_type'] === 'startpage') {
					$pageId = $page['id'];
					break;
				}
			}
		}

		$pageContent = $this->pageContentMapper->findByPageAndNodeRelation($pageId, $nodeRel->getId());

		if ($pageContent === null) {
			$pageContent = new PageContent();
			$pageContent->setPageId($pageId);
			$pageContent->setNodeRelId($nodeRel->getId());
			$pageContent->setOrder($order ?? 100); //FIXME: demand or calc order

			$pageContent = $this->pageContentMapper->insert($pageContent);
		}
		return $pageContent;
	}

	public function removeNodeRelFromAllPages(ContextNodeRelation $nodeRelation): array {
		$contents = $this->pageContentMapper->findByNodeRelation($nodeRelation->getId());
		/** @var PageContent $content */
		foreach ($contents as $content) {
			try {
				$this->pageContentMapper->delete($content);
			} catch (Exception $e) {
				$this->logger->warning('Failed to delete Contexts page content with ID {pcId}', [
					'pcId' => $content->getId(),
					'exception' => $e,
				]);
			}
		}
		return $contents;
	}

	public function updateContentOrder(int $pageId, array $contents): array {
		$updated = [];
		foreach ($contents as $content) {
			try {
				$updated[] = $this->updatePageContent($pageId, $content['id'], $content['order']);
			} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception|InvalidArgumentException $e) {
				$this->logger->info('Could not updated order of content with ID {cID}', [
					'cID' => $content['id'],
					'exception' => $e,
				]);
			}
		}
		return $updated;
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws InvalidArgumentException
	 */
	protected function updatePageContent(int $pageId, int $contentId, int $order): PageContent {
		$pageContent = $this->pageContentMapper->findById($contentId);
		if ($pageContent->getPageId() !== $pageId) {
			throw new InvalidArgumentException('Content does not belong to given page');
		}
		$pageContent->setOrder($order);
		return $this->pageContentMapper->update($pageContent);
	}

	protected function insertPage(Context $context): void {
		$page = new Page();
		$page->setContextId($context->getId());
		$page->setPageType(Page::TYPE_STARTPAGE);
		$this->pageMapper->insert($page);

		$addedPage = $page->jsonSerialize();

		$i = 1;
		foreach ($context->getNodes() as $node) {
			$pageContent = new PageContent();
			$pageContent->setPageId($page->getId());
			$pageContent->setNodeRelId($node['id']);
			$pageContent->setOrder(10 * $i++);

			$this->pageContentMapper->insert($pageContent);

			$addedPage['content'][$pageContent->getId()] = $pageContent->jsonSerialize();
			// the content is already embedded in the page
			unset($addedPage['content'][$pageContent->getId()]['pageId']);
		}

		$context->setPages($addedPage);
	}

	protected function insertNodesFromArray(Context $context, array $nodes): void {
		$addedNodes = [];

		$userId = $context->getOwnerType() === Application::OWNER_TYPE_USER ? $context->getOwnerId() : null;
		foreach ($nodes as $node) {
			try {
				if (!$this->permissionsService->canManageNodeById($node['type'], $node['id'], $userId)) {
					throw new PermissionError(sprintf('Owner cannot manage node %d (type %d)', $node['id'], $node['type']));
				}
				$contextNodeRel = $this->addNodeToContext($context, $node['id'], $node['type'], $node['permissions'] ?? 660);
				$addedNodes[] = $contextNodeRel->jsonSerialize();
			} catch (Exception $e) {
				$this->logger->warning('Could not add node {ntype}/{nid} to context {cid}, skipping.', [
					'app' => Application::APP_ID,
					'ntype' => $node['type'],
					'nid' => $node['id'],
					'cid' => $context['id'],
					'exception' => $e,
				]);
			}
		}
		$context->setNodes($addedNodes);
	}
}
