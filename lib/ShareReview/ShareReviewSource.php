<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\ShareReview;

use OCA\ShareReview\Sources\ISource;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class ShareReviewSource implements ISource {

	private const SHARE_TABLE = 'tables_shares';
	private const TABLES_TABLE = 'tables_tables';
	private const VIEWS_TABLE = 'tables_views';
	private const CONTEXTS_TABLE = 'tables_contexts_context';

	private const NODE_TYPE_TABLE = 'table';
	private const NODE_TYPE_VIEW = 'view';
	private const NODE_TYPE_CONTEXT = 'context';

	private const RECEIVER_TYPE_LINK = 'link';

	public function __construct(
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
	}

	public function getName(): string {
		return 'Tables';
	}

	public function getShares(): array {
		return [];
	}

	public function deleteShare($shareId): bool {
		return false;
	}
}
