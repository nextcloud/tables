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
	 * [api v3] Get information about the requests context
	 *
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
	 * @param array $nodes optional nodes to be connected to this context
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesContext, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Tables returned
	 */
	public function create(string $name, string $iconName, string $description = '', array $nodes = []): DataResponse {
		try {
			return new DataResponse($this->contextService->create($name, $iconName, $description, $nodes, $this->userId, 0)->jsonSerialize());
		} catch (Exception $e) {
			return $this->handleError($e);
		}
	}

	/**
	 * @NoAdminRequired
	 * @CanManageContext
	 */
	public function update(int $contextId, ?string $name, ?string $iconName, ?string $description): DataResponse {
		try {
			return new DataResponse($this->contextService->update($contextId, $name, $iconName, $description)->jsonSerialize());
		} catch (Exception|MultipleObjectsReturnedException $e) {
			return $this->handleError($e);
		} catch (DoesNotExistException $e) {
			return $this->handleNotFoundError(new NotFoundError($e->getMessage(), $e->getCode(), $e));
		}
	}

	/**
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
	 * @NoAdminRequired
	 * @CanManageNode
	 */
	public function addNode(int $contextId, int $nodeId, int $nodeType, int $permissions, ?int $order = null): DataResponse {
		try {
			$rel = $this->contextService->addNodeToContextById($contextId, $nodeId, $nodeType, $permissions, $this->userId);
			$this->contextService->addNodeRelToPage($rel, $order);
			$context = $this->contextService->findById($rel->getContextId(), $this->userId);
			return new DataResponse($context->jsonSerialize());
		} catch (DoesNotExistException $e) {
			return $this->handleNotFoundError(new NotFoundError($e->getMessage(), $e->getCode(), $e));
		} catch (MultipleObjectsReturnedException|Exception|InternalError $e) {
			return $this->handleError($e);
		}
	}

	/**
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
		} catch (DoesNotExistException $e) {
			$this->handleNotFoundError(new NotFoundError($e->getMessage(), $e->getCode(), $e));
		} catch (MultipleObjectsReturnedException|Exception $e) {
			$this->handleError($e);
		}

		return new DataResponse();
	}

	/**
	 * @NoAdminRequired
	 * @CanManageContext
	 */
	public function updateContentOrder(int $contextId, int $pageId, array $content): DataResponse {
		$context = $this->contextService->findById($contextId, $this->userId);
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
