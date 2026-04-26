<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Controller;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Middleware\Attribute\RequirePermission;
use OCA\Tables\Model\FormattingRuleInput;
use OCA\Tables\Model\FormattingRuleSetInput;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Service\FormattingService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\CORS;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TablesFormattingRuleSet from ResponseDefinitions
 * @psalm-import-type TablesFormattingRule from ResponseDefinitions
 */
class FormattingApiController extends ApiController {
	public function __construct(
		IRequest $request,
		protected LoggerInterface $logger,
		protected IL10N $n,
		protected string $userId,
		protected FormattingService $formattingService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Create a new formatting rule set for a view
	 *
	 * @param int $viewId View ID
	 * @param string $title Rule set title
	 * @param string $targetType Target type: 'row' or 'column'
	 * @param int|null $targetCol Target column ID (required when targetType is 'column')
	 * @param string $mode Evaluation mode: 'first-match' or 'all-matches'
	 * @param bool $enabled Whether the rule set is enabled
	 * @param list<array{title?: string, enabled?: bool, condition?: array{groups: list<array{conditions: list<array{columnId: int, columnType: string, operator: string, value?: string|int|float|bool, values?: list<string|int|float>}>}>}, format?: array{backgroundColor?: string, textColor?: string, fontWeight?: 'bold', fontStyle?: 'italic', textDecoration?: 'strikethrough'|'underline'}}> $rules List of rule definitions
	 * @return DataResponse<Http::STATUS_OK, TablesFormattingRuleSet, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Rule set created
	 * 400: Invalid request parameters
	 * 403: No permissions
	 * 404: View not found
	 * 500: Internal error
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	#[UserRateLimit(limit: 10, period: 60)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function createRuleSet(
		int $viewId,
		string $title = '',
		string $targetType = '',
		?int $targetCol = null,
		string $mode = '',
		bool $enabled = true,
		array $rules = [],
	): DataResponse {
		try {
			$input = FormattingRuleSetInput::createFromInputArray([
				'title' => $title,
				'targetType' => $targetType,
				'targetCol' => $targetCol,
				'mode' => $mode,
				'enabled' => $enabled,
				'rules' => $rules,
			]);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		try {
			return new DataResponse($this->formattingService->createRuleSet($viewId, $this->userId, $input));
		} catch (PermissionError $e) {
			$this->logger->warning($e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (NotFoundError $e) {
			$this->logger->info($e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (InternalError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Update a formatting rule set (replaces the full rules array)
	 *
	 * @param int $viewId View ID
	 * @param string $id Rule set ID
	 * @param string $title Rule set title
	 * @param string $targetType Target type: 'row' or 'column'
	 * @param int|null $targetCol Target column ID
	 * @param string $mode Evaluation mode: 'first-match' or 'all-matches'
	 * @param bool $enabled Whether the rule set is enabled
	 * @param list<array{title?: string, enabled?: bool, condition?: array{groups: list<array{conditions: list<array{columnId: int, columnType: string, operator: string, value?: string|int|float|bool, values?: list<string|int|float>}>}>}, format?: array{backgroundColor?: string, textColor?: string, fontWeight?: 'bold', fontStyle?: 'italic', textDecoration?: 'strikethrough'|'underline'}}> $rules Replacement list of rule definitions
	 * @return DataResponse<Http::STATUS_OK, TablesFormattingRuleSet, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Rule set updated
	 * 400: Invalid request parameters
	 * 403: No permissions
	 * 404: Rule set not found
	 * 500: Internal error
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	#[UserRateLimit(limit: 10, period: 60)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function updateRuleSet(
		int $viewId,
		string $id,
		string $title = '',
		string $targetType = '',
		?int $targetCol = null,
		string $mode = '',
		bool $enabled = true,
		array $rules = [],
	): DataResponse {
		try {
			$input = FormattingRuleSetInput::createFromInputArray([
				'title' => $title,
				'targetType' => $targetType,
				'targetCol' => $targetCol,
				'mode' => $mode,
				'enabled' => $enabled,
				'rules' => $rules,
			]);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		try {
			return new DataResponse($this->formattingService->updateRuleSet($viewId, $id, $this->userId, $input));
		} catch (PermissionError $e) {
			$this->logger->warning($e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (NotFoundError $e) {
			$this->logger->info($e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (InternalError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete a formatting rule set
	 *
	 * @param int $viewId View ID
	 * @param string $id Rule set ID
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Rule set deleted
	 * 403: No permissions
	 * 404: Rule set not found
	 * 500: Internal error
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	#[UserRateLimit(limit: 10, period: 60)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function deleteRuleSet(int $viewId, string $id): DataResponse {
		try {
			$this->formattingService->deleteRuleSet($viewId, $id, $this->userId);
			return new DataResponse([]);
		} catch (PermissionError $e) {
			$this->logger->warning($e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (NotFoundError $e) {
			$this->logger->info($e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (InternalError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Reorder formatting rule sets for a view
	 *
	 * @param int $viewId View ID
	 * @param list<string> $orderedIds Rule set IDs in the desired order
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Rule sets reordered
	 * 403: No permissions
	 * 404: Rule set not found
	 * 500: Internal error
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	#[UserRateLimit(limit: 10, period: 60)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function reorder(int $viewId, array $orderedIds = []): DataResponse {
		try {
			$this->formattingService->reorderRuleSets($viewId, $this->userId, $orderedIds);
			return new DataResponse([]);
		} catch (PermissionError $e) {
			$this->logger->warning($e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (NotFoundError $e) {
			$this->logger->info($e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (InternalError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Create a new rule within a rule set
	 *
	 * @param int $viewId View ID
	 * @param string $ruleSetId Rule set ID
	 * @param string $title Rule title
	 * @param bool $enabled Whether the rule is enabled
	 * @param array{groups: list<array{conditions: list<array{columnId: int, columnType: string, operator: string, value?: string|int|float|bool, values?: list<string|int|float>}>}>} $condition Condition set definition
	 * @param array{backgroundColor?: string, textColor?: string, fontWeight?: 'bold', fontStyle?: 'italic', textDecoration?: 'strikethrough'|'underline'} $format Style definition
	 * @return DataResponse<Http::STATUS_OK, TablesFormattingRule, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Rule created
	 * 400: Invalid request parameters
	 * 403: No permissions
	 * 404: Rule set not found
	 * 500: Internal error
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	#[UserRateLimit(limit: 10, period: 60)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function createRule(
		int $viewId,
		string $ruleSetId,
		string $title = '',
		bool $enabled = true,
		array $condition = ['groups' => []],
		array $format = [],
	): DataResponse {
		try {
			$input = FormattingRuleInput::createFromInputArray([
				'title' => $title,
				'enabled' => $enabled,
				'condition' => $condition,
				'format' => $format,
			]);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		try {
			return new DataResponse($this->formattingService->createRule($viewId, $ruleSetId, $this->userId, $input));
		} catch (PermissionError $e) {
			$this->logger->warning($e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (NotFoundError $e) {
			$this->logger->info($e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (InternalError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Update an existing rule
	 *
	 * @param int $viewId View ID
	 * @param string $ruleSetId Rule set ID
	 * @param string $id Rule ID
	 * @param string $title Rule title
	 * @param bool $enabled Whether the rule is enabled
	 * @param array{groups: list<array{conditions: list<array{columnId: int, columnType: string, operator: string, value?: string|int|float|bool, values?: list<string|int|float>}>}>} $condition Condition set definition
	 * @param array{backgroundColor?: string, textColor?: string, fontWeight?: 'bold', fontStyle?: 'italic', textDecoration?: 'strikethrough'|'underline'} $format Style definition
	 * @return DataResponse<Http::STATUS_OK, TablesFormattingRule, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Rule updated
	 * 400: Invalid request parameters
	 * 403: No permissions
	 * 404: Rule not found
	 * 500: Internal error
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	#[UserRateLimit(limit: 10, period: 60)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function updateRule(
		int $viewId,
		string $ruleSetId,
		string $id,
		string $title = '',
		bool $enabled = true,
		array $condition = ['groups' => []],
		array $format = [],
	): DataResponse {
		try {
			$input = FormattingRuleInput::createFromInputArray([
				'title' => $title,
				'enabled' => $enabled,
				'condition' => $condition,
				'format' => $format,
			]);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		try {
			return new DataResponse($this->formattingService->updateRule($viewId, $ruleSetId, $id, $this->userId, $input));
		} catch (PermissionError $e) {
			$this->logger->warning($e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (NotFoundError $e) {
			$this->logger->info($e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (InternalError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Delete an existing rule
	 *
	 * @param int $viewId View ID
	 * @param string $ruleSetId Rule set ID
	 * @param string $id Rule ID
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Rule deleted
	 * 403: No permissions
	 * 404: Rule not found
	 * 500: Internal error
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	#[UserRateLimit(limit: 10, period: 60)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function deleteRule(int $viewId, string $ruleSetId, string $id): DataResponse {
		try {
			$this->formattingService->deleteRule($viewId, $ruleSetId, $id, $this->userId);
			return new DataResponse([]);
		} catch (PermissionError $e) {
			$this->logger->warning($e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (NotFoundError $e) {
			$this->logger->info($e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (InternalError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
