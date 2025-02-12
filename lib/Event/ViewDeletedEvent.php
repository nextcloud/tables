<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Event;

use OCA\Tables\Db\View;
use OCP\EventDispatcher\Event;

final class ViewDeletedEvent extends Event {
	public function __construct(
		protected View $view,
	) {
		parent::__construct();
	}

	public function getView(): View {
		return $this->view;
	}
}
