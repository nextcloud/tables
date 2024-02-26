<?php

declare(strict_types=1);

namespace OCA\Tables\Service;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\Context;
use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Db\ContextNodeRelation;
use OCA\Tables\Db\ContextNodeRelationMapper;
use OCA\Tables\Db\Page;
use OCA\Tables\Db\PageContent;
use OCA\Tables\Db\PageContentMapper;
use OCA\Tables\Db\PageMapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\PermissionError;
use OCP\DB\Exception;
use Psr\Log\LoggerInterface;

class ContextService {

	private ContextMapper $contextMapper;
	private bool $isCLI;
	private LoggerInterface $logger;
	private ContextNodeRelationMapper $contextNodeRelMapper;
	private PageMapper $pageMapper;
	private PageContentMapper $pageContentMapper;
	private PermissionsService $permissionsService;

	public function __construct(
		ContextMapper             $contextMapper,
		ContextNodeRelationMapper $contextNodeRelationMapper,
		PageMapper                $pageMapper,
		PageContentMapper         $pageContentMapper,
		LoggerInterface           $logger,
		PermissionsService        $permissionsService,
		bool                      $isCLI,
	) {
		$this->contextMapper = $contextMapper;
		$this->isCLI = $isCLI;
		$this->logger = $logger;
		$this->contextNodeRelMapper = $contextNodeRelationMapper;
		$this->pageMapper = $pageMapper;
		$this->pageContentMapper = $pageContentMapper;
		$this->permissionsService = $permissionsService;
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
			$contextNodeRel = new ContextNodeRelation();
			$contextNodeRel->setContextId($context->getId());
			$contextNodeRel->setNodeId($node['id']);
			$contextNodeRel->setNodeType($node['type']);
			$contextNodeRel->setPermissions($node['permissions'] ?? 660);

			try {
				if (!$this->permissionsService->canManageNodeById($node['type'], $node['id'], $userId)) {
					throw new PermissionError(sprintf('Owner cannot manage node %d (type %d)', $node['id'], $node['type']));
				}

				$this->contextNodeRelMapper->insert($contextNodeRel);
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
