<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service\ValueObject;

use InvalidArgumentException;
use OCA\Tables\Constants\ShareReceiverType;
use OCA\Tables\Service\ValueObject\ShareCreate;
use PHPUnit\Framework\TestCase;

class ShareCreateTest extends TestCase {

	/**
	 * @dataProvider provideSupportedCombinations
	 */
	public function testAcceptsAReceiverTypeTheNodeTypeResolves(string $nodeType, string $receiverType): void {
		$this->assertSame($receiverType, $this->buildShare($nodeType, $receiverType)->getReceiverType());
	}

	public static function provideSupportedCombinations(): array {
		return [
			'user share on a context' => ['context', ShareReceiverType::USER],
			'group share on a context' => ['context', ShareReceiverType::GROUP],
			'user share on a table' => ['table', ShareReceiverType::USER],
			'group share on a table' => ['table', ShareReceiverType::GROUP],
			'team share on a table' => ['table', ShareReceiverType::CIRCLE],
			'link share on a table' => ['table', ShareReceiverType::LINK],
			'federated share on a table' => ['table', ShareReceiverType::REMOTE],
			'team share on a view' => ['view', ShareReceiverType::CIRCLE],
		];
	}

	/**
	 * ContextMapper::applyOwnedOrSharedQuery() matches context shares by user
	 * and by group only, so a context share to anything else is stored without
	 * ever granting access to a single member.
	 *
	 * @dataProvider provideUnsupportedCombinations
	 */
	public function testRejectsAReceiverTypeTheNodeTypeCannotResolve(string $nodeType, string $receiverType): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage(sprintf('A %s cannot be shared with a receiver of type "%s"', $nodeType, $receiverType));

		$this->buildShare($nodeType, $receiverType);
	}

	public static function provideUnsupportedCombinations(): array {
		return [
			'team share on a context' => ['context', ShareReceiverType::CIRCLE],
			'link share on a context' => ['context', ShareReceiverType::LINK],
			'federated share on a context' => ['context', ShareReceiverType::REMOTE],
			'unknown receiver type on a context' => ['context', 'nobody'],
			'unknown receiver type on a table' => ['table', 'nobody'],
			'unknown receiver type on a view' => ['view', 'nobody'],
		];
	}

	private function buildShare(string $nodeType, string $receiverType): ShareCreate {
		return new ShareCreate(1, $nodeType, 'receiver-id', $receiverType, true, false, false, false, false, 0);
	}
}
