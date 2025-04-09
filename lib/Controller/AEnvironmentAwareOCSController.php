<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Controller;

use OCA\Tables\Db\Context;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\View;

abstract class AEnvironmentAwareOCSController extends AOCSController {
	protected ?Table $table = null;
	protected ?View $view = null;
	protected ?Context $context = null;

	public function getTable(): ?Table {
		return $this->table;
	}

	public function setTable(?Table $table): void {
		$this->table = $table;
	}

	public function getView(): ?View {
		return $this->view;
	}

	public function setView(?View $view): void {
		$this->view = $view;
	}

	public function getContext(): ?Context {
		return $this->context;
	}

	public function setContext(?Context $context): void {
		$this->context = $context;
	}
}
