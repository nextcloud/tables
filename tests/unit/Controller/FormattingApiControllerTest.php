<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Controller;

use OCA\Tables\Controller\FormattingApiController;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Model\FormattingRuleInput;
use OCA\Tables\Model\FormattingRuleSetInput;
use OCA\Tables\Service\FormattingService;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class FormattingApiControllerTest extends TestCase {
	private FormattingApiController $controller;
	private $formattingService;

	protected function setUp(): void {
		$this->formattingService = $this->createMock(FormattingService::class);

		$this->controller = new FormattingApiController(
			$this->createMock(IRequest::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(IL10N::class),
			'user1',
			$this->formattingService,
		);
	}

	public function testCreateRuleSetReturnsCreatedRuleSet(): void {
		$created = ['id' => 'rs-1', 'title' => 'Highlights', 'rules' => []];
		$this->formattingService->expects($this->once())->method('createRuleSet')
			->with(5, 'user1', $this->callback(function (FormattingRuleSetInput $input): bool {
				return $input->getTitle() === 'Highlights'
					&& $input->getTargetType() === 'row'
					&& $input->getMode() === 'first-match';
			}))
			->willReturn($created);

		$response = $this->controller->createRuleSet(5, 'Highlights', 'row', null, 'first-match');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($created, $response->getData());
	}

	public function testCreateRuleSetMapsAServiceValidationErrorToBadRequest(): void {
		$this->formattingService->method('createRuleSet')
			->willThrowException(new \InvalidArgumentException('Maximum of 50 rule sets per view exceeded'));

		$response = $this->controller->createRuleSet(5, 'Highlights', 'row', null, 'first-match');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame('Maximum of 50 rule sets per view exceeded', $response->getData()['message']);
	}

	public function testCreateRuleSetReturnsBadRequestForInvalidInputWithoutCallingService(): void {
		$this->formattingService->expects($this->never())->method('createRuleSet');

		$response = $this->controller->createRuleSet(5, 'Broken', 'cell', null, 'first-match');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame('targetType must be "row" or "column"', $response->getData()['message']);
	}

	/** @return iterable<string, array{\Throwable, int}> */
	public static function serviceErrorProvider(): iterable {
		yield 'permission' => [new PermissionError('no'), Http::STATUS_FORBIDDEN];
		yield 'not found' => [new NotFoundError('missing'), Http::STATUS_NOT_FOUND];
		yield 'internal' => [new InternalError('boom'), Http::STATUS_INTERNAL_SERVER_ERROR];
	}

	/** @dataProvider serviceErrorProvider */
	public function testCreateRuleSetMapsServiceErrorsToStatusCodes(\Throwable $error, int $status): void {
		$this->formattingService->method('createRuleSet')->willThrowException($error);

		$response = $this->controller->createRuleSet(5, 'Highlights', 'row', null, 'first-match');

		$this->assertSame($status, $response->getStatus());
		$this->assertSame($error->getMessage(), $response->getData()['message']);
	}

	public function testUpdateRuleSetPassesIdAndInput(): void {
		$this->formattingService->expects($this->once())->method('updateRuleSet')
			->with(5, 'rs-1', 'user1', $this->callback(function (FormattingRuleSetInput $input): bool {
				return $input->getTargetType() === 'column' && $input->getTargetCol() === 9 && !$input->isEnabled();
			}))
			->willReturn(['id' => 'rs-1']);

		$response = $this->controller->updateRuleSet(5, 'rs-1', 'T', 'column', 9, 'all-matches', false);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testUpdateRuleSetReturnsBadRequestWhenColumnTargetLacksColumn(): void {
		$this->formattingService->expects($this->never())->method('updateRuleSet');

		$response = $this->controller->updateRuleSet(5, 'rs-1', 'T', 'column', null, 'first-match');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	/** @dataProvider serviceErrorProvider */
	public function testDeleteRuleSetMapsServiceErrors(\Throwable $error, int $status): void {
		$this->formattingService->method('deleteRuleSet')->willThrowException($error);

		$this->assertSame($status, $this->controller->deleteRuleSet(5, 'rs-1')->getStatus());
	}

	public function testDeleteRuleSetReturnsEmptyOkResponse(): void {
		$this->formattingService->expects($this->once())->method('deleteRuleSet')->with(5, 'rs-1', 'user1');

		$response = $this->controller->deleteRuleSet(5, 'rs-1');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([], $response->getData());
	}

	public function testReorderForwardsOrderedIds(): void {
		$this->formattingService->expects($this->once())->method('reorderRuleSets')
			->with(5, 'user1', ['rs-2', 'rs-1']);

		$this->assertSame(Http::STATUS_OK, $this->controller->reorder(5, ['rs-2', 'rs-1'])->getStatus());
	}

	/** @dataProvider serviceErrorProvider */
	public function testReorderMapsServiceErrors(\Throwable $error, int $status): void {
		$this->formattingService->method('reorderRuleSets')->willThrowException($error);

		$this->assertSame($status, $this->controller->reorder(5, ['rs-1'])->getStatus());
	}

	public function testCreateRuleMapsStyleParameterToFormat(): void {
		$this->formattingService->expects($this->once())->method('createRule')
			->with(5, 'rs-1', 'user1', $this->callback(function (FormattingRuleInput $input): bool {
				return $input->getTitle() === 'Red'
					&& $input->getFormat()->toArray() === ['backgroundColor' => '#ff0000']
					&& $input->getCondition()->collectColumnIds() === [3];
			}))
			->willReturn(['id' => 'rule-1']);

		$response = $this->controller->createRule(
			5,
			'rs-1',
			'Red',
			true,
			['groups' => [['conditions' => [['columnId' => 3, 'columnType' => 'text-line', 'operator' => 'contains', 'value' => 'x']]]]],
			['backgroundColor' => '#ff0000'],
		);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['id' => 'rule-1'], $response->getData());
	}

	public function testCreateRuleReturnsBadRequestForEmptyConditionGroups(): void {
		$this->formattingService->expects($this->never())->method('createRule');

		$response = $this->controller->createRule(5, 'rs-1', 'Red');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame('At least one condition group is required', $response->getData()['message']);
	}

	public function testCreateRuleReturnsBadRequestForUnknownStyleKey(): void {
		$this->formattingService->expects($this->never())->method('createRule');

		$response = $this->controller->createRule(
			5,
			'rs-1',
			'Red',
			true,
			['groups' => [['conditions' => [['columnId' => 3, 'columnType' => 'text-line', 'operator' => 'is-empty']]]]],
			['shadow' => '1px'],
		);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertStringContainsString('Unknown style key', $response->getData()['message']);
	}

	/** @dataProvider serviceErrorProvider */
	public function testUpdateRuleMapsServiceErrors(\Throwable $error, int $status): void {
		$this->formattingService->method('updateRule')->willThrowException($error);

		$response = $this->controller->updateRule(
			5,
			'rs-1',
			'rule-1',
			'Red',
			true,
			['groups' => [['conditions' => [['columnId' => 3, 'columnType' => 'text-line', 'operator' => 'is-empty']]]]],
			[],
		);

		$this->assertSame($status, $response->getStatus());
	}

	public function testUpdateRulePassesAllIdentifiers(): void {
		$this->formattingService->expects($this->once())->method('updateRule')
			->with(5, 'rs-1', 'rule-1', 'user1', $this->isInstanceOf(FormattingRuleInput::class))
			->willReturn(['id' => 'rule-1', 'enabled' => false]);

		$response = $this->controller->updateRule(
			5,
			'rs-1',
			'rule-1',
			'Red',
			false,
			['groups' => [['conditions' => [['columnId' => 3, 'columnType' => 'text-line', 'operator' => 'is-empty']]]]],
			[],
		);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertFalse($response->getData()['enabled']);
	}

	public function testDeleteRuleReturnsEmptyOkResponse(): void {
		$this->formattingService->expects($this->once())->method('deleteRule')->with(5, 'rs-1', 'rule-1', 'user1');

		$response = $this->controller->deleteRule(5, 'rs-1', 'rule-1');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([], $response->getData());
	}

	/** @dataProvider serviceErrorProvider */
	public function testDeleteRuleMapsServiceErrors(\Throwable $error, int $status): void {
		$this->formattingService->method('deleteRule')->willThrowException($error);

		$this->assertSame($status, $this->controller->deleteRule(5, 'rs-1', 'rule-1')->getStatus());
	}
}
