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
	private const MAX_RULE_SET_ID_LENGTH = 36;

	public function __construct(
		IRequest $request,
		protected LoggerInterface $logger,
		protected IL10N $l10n,
		protected string $userId,
		protected FormattingService $formattingService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * @return DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 */
	private function errorResponse(\InvalidArgumentException|PermissionError|NotFoundError|InternalError $e): DataResponse {
		if ($e instanceof PermissionError) {
			return $this->handlePermissionError($e);
		}
		if ($e instanceof InternalError) {
			return $this->handleInternalError($e);
		}
		$this->logger->info($e->getMessage(), ['exception' => $e]);
		$status = $e instanceof NotFoundError ? Http::STATUS_NOT_FOUND : Http::STATUS_BAD_REQUEST;

		return new DataResponse(['message' => $e->getMessage()], $status);
	}

	/**
	 * @return DataResponse<Http::STATUS_FORBIDDEN, array{message: string}, array{}>
	 */
	private function handlePermissionError(PermissionError $e): DataResponse {
		$this->logger->warning('A permission error occurred: ' . $e->getMessage(), ['exception' => $e]);
		return new DataResponse(['message' => $this->l10n->t('A permission error occurred. More details can be found in the logs. Please reach out to your administration.')], Http::STATUS_FORBIDDEN);
	}

	/**
	 * @return DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 */
	private function handleInternalError(InternalError $e): DataResponse {
		$this->logger->error('An internal error or exception occurred: ' . $e->getMessage(), ['exception' => $e]);
		return new DataResponse(['message' => $this->l10n->t('An unexpected error occurred. More details can be found in the logs. Please reach out to your administration.')], Http::STATUS_INTERNAL_SERVER_ERROR);
	}

	/**
	 * Create a formatting rule set on a view
	 *
	 * @param int $viewId View ID
	 * @param string $title Rule set title
	 * @param string $targetType Target type: 'row' or 'column'
	 * @param int|null $targetCol Target column ID (required when targetType is 'column')
	 * @param string $mode Evaluation mode: 'first-match' or 'all-matches'
	 * @param 'circle'|'star'|'flag'|'alert'|'check'|null $marker Marker shown on what the rule set paints, so it is not told by colour alone
	 * @param bool $enabled Whether the rule set is enabled
	 * @param list<array{title?: string, enabled?: bool, condition?: array{groups: list<array{conditions: list<array{columnId: int, columnType: string, operator: 'begins-with'|'ends-with'|'contains'|'contains-item'|'does-not-contain'|'is-equal'|'is-not-equal'|'is-greater-than'|'is-greater-than-or-equal'|'is-lower-than'|'is-lower-than-or-equal'|'is-empty'|'is-not-empty', value?: string|int|float|bool|list<mixed>}>}>}, format?: array{backgroundColor?: string, textColor?: string, fontWeight?: 'bold', fontStyle?: 'italic', textDecoration?: 'strikethrough'|'underline'}}> $rules List of rule definitions
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
	#[UserRateLimit(limit: 20, period: 60)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function createRuleSet(
		int $viewId,
		string $title = '',
		string $targetType = '',
		?int $targetCol = null,
		string $mode = '',
		bool $enabled = true,
		array $rules = [],
		?string $marker = null,
	): DataResponse {
		try {
			$input = FormattingRuleSetInput::createFromInputArray([
				'title' => $title,
				'targetType' => $targetType,
				'targetCol' => $targetCol,
				'mode' => $mode,
				'marker' => $marker,
				'enabled' => $enabled,
				'rules' => $rules,
			]);
			return new DataResponse($this->formattingService->createRuleSet($viewId, $this->userId, $input));
		} catch (\InvalidArgumentException|PermissionError|NotFoundError|InternalError $e) {
			return $this->errorResponse($e);
		}
	}

	/**
	 * Update a formatting rule set of a view
	 *
	 * @param int $viewId View ID
	 * @param string $id Rule set ID
	 * @param string $title Rule set title
	 * @param string $targetType Target type: 'row' or 'column'
	 * @param int|null $targetCol Target column ID
	 * @param string $mode Evaluation mode: 'first-match' or 'all-matches'
	 * @param 'circle'|'star'|'flag'|'alert'|'check'|null $marker Marker shown on what the rule set paints, so it is not told by colour alone
	 * @param bool $enabled Whether the rule set is enabled
	 * @param list<array{title?: string, enabled?: bool, condition?: array{groups: list<array{conditions: list<array{columnId: int, columnType: string, operator: 'begins-with'|'ends-with'|'contains'|'contains-item'|'does-not-contain'|'is-equal'|'is-not-equal'|'is-greater-than'|'is-greater-than-or-equal'|'is-lower-than'|'is-lower-than-or-equal'|'is-empty'|'is-not-empty', value?: string|int|float|bool|list<mixed>}>}>}, format?: array{backgroundColor?: string, textColor?: string, fontWeight?: 'bold', fontStyle?: 'italic', textDecoration?: 'strikethrough'|'underline'}}> $rules Replacement list of rule definitions
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
	#[UserRateLimit(limit: 20, period: 60)]
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
		?string $marker = null,
	): DataResponse {
		try {
			$input = FormattingRuleSetInput::createFromInputArray([
				'title' => $title,
				'targetType' => $targetType,
				'targetCol' => $targetCol,
				'mode' => $mode,
				'marker' => $marker,
				'enabled' => $enabled,
				'rules' => $rules,
			]);
			return new DataResponse($this->formattingService->updateRuleSet($viewId, $id, $this->userId, $input));
		} catch (\InvalidArgumentException|PermissionError|NotFoundError|InternalError $e) {
			return $this->errorResponse($e);
		}
	}

	/**
	 * Delete a formatting rule set from a view
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
	#[UserRateLimit(limit: 20, period: 60)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function deleteRuleSet(int $viewId, string $id): DataResponse {
		try {
			$this->formattingService->deleteRuleSet($viewId, $id, $this->userId);
			return new DataResponse([]);
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (NotFoundError $e) {
			$this->logger->info($e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (InternalError $e) {
			return $this->handleInternalError($e);
		}
	}

	/**
	 * Reorder the formatting rule sets of a view
	 *
	 * @param int $viewId View ID
	 * @param list<string> $orderedIds Rule set IDs in the desired order
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Rule sets reordered
	 * 400: Invalid request parameters
	 * 403: No permissions
	 * 404: Rule set not found
	 * 500: Internal error
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[CORS]
	#[RequirePermission(permission: Application::PERMISSION_MANAGE, type: Application::NODE_TYPE_VIEW, idParam: 'viewId')]
	#[UserRateLimit(limit: 20, period: 60)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function reorder(int $viewId, array $orderedIds = []): DataResponse {
		try {
			$orderedIds = self::assertRuleSetIdList($orderedIds);
		} catch (\InvalidArgumentException|PermissionError|NotFoundError|InternalError $e) {
			return $this->errorResponse($e);
		}

		try {
			$this->formattingService->reorderRuleSets($viewId, $this->userId, $orderedIds);
			return new DataResponse([]);
		} catch (\InvalidArgumentException|PermissionError|NotFoundError|InternalError $e) {
			return $this->errorResponse($e);
		}
	}

	/**
	 * Create a rule in a formatting rule set
	 *
	 * @param int $viewId View ID
	 * @param string $ruleSetId Rule set ID
	 * @param string $title Rule title
	 * @param bool $enabled Whether the rule is enabled
	 * @param array{groups: list<array{conditions: list<array{columnId: int, columnType: string, operator: 'begins-with'|'ends-with'|'contains'|'contains-item'|'does-not-contain'|'is-equal'|'is-not-equal'|'is-greater-than'|'is-greater-than-or-equal'|'is-lower-than'|'is-lower-than-or-equal'|'is-empty'|'is-not-empty', value?: string|int|float|bool|list<mixed>}>}>} $condition Condition set definition
	 * @param array{backgroundColor?: string, textColor?: string, fontWeight?: 'bold', fontStyle?: 'italic', textDecoration?: 'strikethrough'|'underline'} $style Style definition
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
	#[UserRateLimit(limit: 20, period: 60)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function createRule(
		int $viewId,
		string $ruleSetId,
		string $title = '',
		bool $enabled = true,
		array $condition = ['groups' => []],
		array $style = [],
	): DataResponse {
		try {
			$input = FormattingRuleInput::createFromInputArray([
				'title' => $title,
				'enabled' => $enabled,
				'condition' => $condition,
				'format' => $style,
			]);
			return new DataResponse($this->formattingService->createRule($viewId, $ruleSetId, $this->userId, $input));
		} catch (\InvalidArgumentException|PermissionError|NotFoundError|InternalError $e) {
			return $this->errorResponse($e);
		}
	}

	/**
	 * Update a rule of a formatting rule set
	 *
	 * @param int $viewId View ID
	 * @param string $ruleSetId Rule set ID
	 * @param string $id Rule ID
	 * @param string $title Rule title
	 * @param bool $enabled Whether the rule is enabled
	 * @param array{groups: list<array{conditions: list<array{columnId: int, columnType: string, operator: 'begins-with'|'ends-with'|'contains'|'contains-item'|'does-not-contain'|'is-equal'|'is-not-equal'|'is-greater-than'|'is-greater-than-or-equal'|'is-lower-than'|'is-lower-than-or-equal'|'is-empty'|'is-not-empty', value?: string|int|float|bool|list<mixed>}>}>} $condition Condition set definition
	 * @param array{backgroundColor?: string, textColor?: string, fontWeight?: 'bold', fontStyle?: 'italic', textDecoration?: 'strikethrough'|'underline'} $style Style definition
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
	#[UserRateLimit(limit: 20, period: 60)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function updateRule(
		int $viewId,
		string $ruleSetId,
		string $id,
		string $title = '',
		bool $enabled = true,
		array $condition = ['groups' => []],
		array $style = [],
	): DataResponse {
		try {
			$input = FormattingRuleInput::createFromInputArray([
				'title' => $title,
				'enabled' => $enabled,
				'condition' => $condition,
				'format' => $style,
			]);
			return new DataResponse($this->formattingService->updateRule($viewId, $ruleSetId, $id, $this->userId, $input));
		} catch (\InvalidArgumentException|PermissionError|NotFoundError|InternalError $e) {
			return $this->errorResponse($e);
		}
	}

	/**
	 * Delete a rule from a formatting rule set
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
	#[UserRateLimit(limit: 20, period: 60)]
	#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
	public function deleteRule(int $viewId, string $ruleSetId, string $id): DataResponse {
		try {
			$this->formattingService->deleteRule($viewId, $ruleSetId, $id, $this->userId);
			return new DataResponse([]);
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (NotFoundError $e) {
			$this->logger->info($e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (InternalError $e) {
			return $this->handleInternalError($e);
		}
	}

	/**
	 * @param array $orderedIds
	 * @return list<string>
	 * @throws \InvalidArgumentException
	 */
	private static function assertRuleSetIdList(array $orderedIds): array {
		if (count($orderedIds) > FormattingService::MAX_RULE_SETS_PER_VIEW) {
			throw new \InvalidArgumentException('orderedIds must hold at most ' . FormattingService::MAX_RULE_SETS_PER_VIEW . ' rule set IDs');
		}

		$ids = [];
		foreach ($orderedIds as $id) {
			if (!is_string($id) || $id === '' || strlen($id) > self::MAX_RULE_SET_ID_LENGTH) {
				throw new \InvalidArgumentException('orderedIds must be a list of rule set IDs');
			}
			$ids[] = $id;
		}

		if (count(array_unique($ids)) !== count($ids)) {
			throw new \InvalidArgumentException('orderedIds must not contain duplicate rule set IDs');
		}

		return $ids;
	}
}
