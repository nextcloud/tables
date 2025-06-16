<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Activity;

use OCA\Tables\AppInfo\Application;
use OCP\Activity\IFilter;
use OCP\IL10N;
use OCP\IURLGenerator;

class Filter implements IFilter {
	public function __construct(
		protected IL10N $l,
		protected IURLGenerator $url,
	) {
	}

	public function getIdentifier(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l->t('Tables');
	}

	public function getPriority(): int {
		return 40;
	}

	public function getIcon(): string {
		return $this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'app-dark.svg'));
	}

	public function filterTypes(array $types): array {
		return $types;
	}

	public function allowedApps(): array {
		return [Application::APP_ID];
	}
}
