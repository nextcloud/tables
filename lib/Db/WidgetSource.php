<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\Entity;

/**
 * The node of its application a data widget shows. The widget id is the key;
 * the inherited id is not used.
 *
 * @method getWidgetId(): int
 * @method setWidgetId(int $value): void
 * @method getNodeRelId(): int
 * @method setNodeRelId(int $value): void
 */
class WidgetSource extends Entity {
	protected ?int $widgetId = null;
	protected ?int $nodeRelId = null;

	public function __construct() {
		$this->addType('widgetId', 'integer');
		$this->addType('nodeRelId', 'integer');
	}
}
