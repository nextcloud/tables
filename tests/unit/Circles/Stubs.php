<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Circles\Model {

	/**
	 * Runtime stubs for test runs without the Circles app, which is how CI runs.
	 * Only loaded when the real classes are missing; the shapes mirror the Psalm
	 * stubs in tests/stub.phpstub.
	 */
	class Circle {
		public function getSingleId(): string {
			return '';
		}

		/** @return list<Member> */
		public function getInheritedMembers(): array {
			return [];
		}
	}

	class Member {
		public const TYPE_SINGLE = 0;
		public const TYPE_USER = 1;
		public const TYPE_GROUP = 2;
		public const TYPE_MAIL = 4;
		public const TYPE_CONTACT = 8;
		public const TYPE_CIRCLE = 16;
		public const TYPE_APP = 10000;

		public function getUserId(): string {
			return '';
		}

		public function getUserType(): int {
			return self::TYPE_USER;
		}

		public function getBasedOn(): ?Circle {
			return null;
		}
	}
}

namespace OCA\Circles\Events {

	use OCA\Circles\Model\Circle;
	use OCA\Circles\Model\Member;
	use OCP\EventDispatcher\Event;

	class CircleMemberRemovedEvent extends Event {
		public function getCircle(): Circle {
			return new Circle();
		}

		public function getMember(): ?Member {
			return null;
		}
	}
}
