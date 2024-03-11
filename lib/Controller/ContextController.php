<?php

declare(strict_types=1);

namespace OCA\Tables\Controller;

use OCA\Tables\Db\Context;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Service\ContextService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\Exception;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TablesContext from ResponseDefinitions
 */

class ContextController extends AOCSController {
	private ContextService $contextService;

	public function __construct(
		IRequest        $request,
		LoggerInterface $logger,
		IL10N           $n,
		string          $userId,
		ContextService  $contextService
	) {
		parent::__construct($request, $logger, $n, $userId);
		$this->contextService = $contextService;
		$this->userId = $userId;
	}

	/**
	 * [api v3] Get all contexts available to the requesting person
	 *
	 * Return an empty array if no contexts were found
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesContext[], array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: reporting in available contexts
	 *
	 * @NoAdminRequired
	 */
	public function index(): DataResponse {
		try {
			$contexts = $this->contextService->findAll($this->userId);
			return new DataResponse($this->contextsToArray($contexts));
		} catch (InternalError|Exception $e) {
			return $this->handleError($e);
		}
	}

	/**
	 * [api v2] Get information about the requests context
	 *
	 * @param int $contextId ID of the context
	 * @return DataResponse<Http::STATUS_OK, TablesContext, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: returning the full context information
	 * 404: context not found or not available anymore
	 *
	 * @NoAdminRequired
	 */
	public function show(int $contextId): DataResponse {
		try {
			$context = $this->contextService->findById($contextId, $this->userId);
			return new DataResponse($context->jsonSerialize());
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		} catch (InternalError|Exception $e) {
			return $this->handleError($e);
		}
	}

	/**
	 * [api v2] Create a new context and return it
	 *
	 * @NoAdminRequired
	 *
	 * @param string $name Name of the context
	 * @param string $iconName Material design icon name of the context
	 * @param string $description Descriptive text of the context
	 * @param array{id: int, type: int, permissions: int} $nodes optional nodes to be connected to this context
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesContext, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: returning the full context information
	 */
	public function create(string $name, string $iconName, string $description = '', array $nodes = []): DataResponse {
		try {
			return new DataResponse($this->contextService->create($name, $iconName, $description, $nodes, $this->userId, 0)->jsonSerialize());
		} catch (Exception $e) {
			return $this->handleError($e);
		}
	}

	/**
	 * [api v2] Update an existing context and return it
	 *
	 * @param int $contextId ID of the context
	 * @param ?string $name provide this parameter to set a new name
	 * @param ?string $iconName provide this parameter to set a new icon
	 * @param ?string $description provide this parameter to set a new description
	 * @param ?array{id: int, type: int, permissions: int, order: int} $nodes provide this parameter to set a new list of nodes.
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesContext, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: returning the full context information
	 * 403: No permissions
	 * 404: Not found
	 *
	 * @NoAdminRequired
	 * @CanManageContext
	 */
	public function update(int $contextId, ?string $name, ?string $iconName, ?string $description, ?array $nodes): DataResponse {
		try {
			return new DataResponse($this->contextService->update(
				$contextId,
				$this->userId,
				$name,
				$iconName,
				$description,
				$nodes,
			)->jsonSerialize());
		} catch (Exception|MultipleObjectsReturnedException $e) {
			return $this->handleError($e);
		} catch (DoesNotExistException $e) {
			return $this->handleNotFoundError(new NotFoundError($e->getMessage(), $e->getCode(), $e));
		}
	}

	/**
	 * [api v2] Transfer the ownership of a context and return it
	 *
	 * @param int $contextId ID of the context
	 * @param string $newOwnerId ID of the new owner
	 * @param int $newOwnerType any Application::OWNER_TYPE_* constant
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesContext, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Ownership transferred
	 * 400: Invalid request
	 * 403: No permissions
	 * 404: Not found
	 *
	 * @NoAdminRequired
	 * @CanManageContext
	 *
	 * @psalm-param int<0, max> $contextId
	 * @psalm-param int<0, 0> $newOwnerType
	 */
	public function transfer(int $contextId, string $newOwnerId, int $newOwnerType = 0): DataResponse {
		try {
			return new DataResponse($this->contextService->transfer($contextId, $newOwnerId, $newOwnerType)->jsonSerialize());
		} catch (Exception|MultipleObjectsReturnedException $e) {
			return $this->handleError($e);
		} catch (DoesNotExistException $e) {
			return $this->handleNotFoundError(new NotFoundError($e->getMessage(), $e->getCode(), $e));
		} catch (BadRequestError $e) {
			return $this->handleBadRequestError($e);
		}
	}

	/**
	 * [api v2] Add a node to a Context
	 *
	 * @param int $contextId ID of the context
	 * @param int $nodeId ID of the node
	 * @param int $nodeType any Application::NODE_TYPE_* constant
	 * @param int $permissions bitmask of the permissions for context recipients
	 * @param ?int $order in which order the node should appear within the context
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesContext, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Node added successfully
	 * 403: No permissions
	 * 404: Not found
	 *
	 * @NoAdminRequired
	 * @CanManageNode
	 */
	public function addNode(int $contextId, int $nodeId, int $nodeType, int $permissions, ?int $order = null): DataResponse {
		try {
			$rel = $this->contextService->addNodeToContextById($contextId, $nodeId, $nodeType, $permissions, $this->userId);
			$this->contextService->addNodeRelToPage($rel, $order);
			$context = $this->contextService->findById($rel->getContextId(), $this->userId);
			return new DataResponse($context->jsonSerialize());
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		} catch (DoesNotExistException $e) {
			return $this->handleNotFoundError(new NotFoundError($e->getMessage(), $e->getCode(), $e));
		} catch (MultipleObjectsReturnedException|Exception|InternalError $e) {
			return $this->handleError($e);
		}
	}

	/**
	 * [api v2] Remove a node from a Context
	 *
	 * @param int $contextId ID of the context
	 * @param int $nodeRelId ID of the node-in-context relation
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesContext, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Node removed successfully
	 * 400: Invalid request
	 * 403: No permissions
	 * 404: Not found
	 *
	 * @NoAdminRequired
	 * @CanManageContext
	 */
	public function removeNode(int $contextId, int $nodeRelId): DataResponse {
		// we could do without the contextId, however it is used by the Permission Middleware
		// and also results in a more consistent endpoint url
		try {
			$context = $this->contextService->findById($contextId, $this->userId);
			if (!isset($context->getNodes()[$nodeRelId])) {
				return $this->handleBadRequestError(new BadRequestError('Node Relation ID not found in given Context'));
			}
			$nodeRelation = $this->contextService->removeNodeFromContextById($nodeRelId);
			$this->contextService->removeNodeRelFromAllPages($nodeRelation);
			$context = $this->contextService->findById($contextId, $this->userId);
			return new DataResponse($context->jsonSerialize());
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		} catch (DoesNotExistException $e) {
			return $this->handleNotFoundError(new NotFoundError($e->getMessage(), $e->getCode(), $e));
		} catch (MultipleObjectsReturnedException|Exception|InternalError $e) {
			return $this->handleError($e);
		}
	}

	/**
	 * [api v2] Update the order on a page of a context
	 *
	 * @param int $contextId ID of the context
	 * @param int $pageId ID of the page
	 * @param array{id: int, order: int} $content content items with it and order values
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesContext, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * @NoAdminRequired
	 * @CanManageContext
	 *
	 * 200: content updated successfully
	 * 400: Invalid request
	 * 403: No permissions
	 * 404: Not found
	 */
	public function updateContentOrder(int $contextId, int $pageId, array $content): DataResponse {
		try {
			$context = $this->contextService->findById($contextId, $this->userId);
		} catch (Exception|InternalError $e) {
			return $this->handleError($e);
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		}
		if (!isset($context->getPages()[$pageId])) {
			return $this->handleBadRequestError(new BadRequestError('Page not found in given Context'));
		}

		return new DataResponse($this->contextService->updateContentOrder($pageId, $content));
	}

	/**
	 * @param Context[] $contexts
	 * @return array
	 */
	protected function contextsToArray(array $contexts): array {
		$result = [];
		foreach ($contexts as $context) {
			$result[] = $context->jsonSerialize();
		}
		return $result;
	}
}
