<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use InvalidArgumentException;
use OCA\Tables\Constants\ViewUpdatableParameters;
use OCA\Tables\Db\View;
use OCA\Tables\Model\ViewUpdateInput;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;

class ViewLayoutTest extends DatabaseTestCase {
	public function testMigrationAddsLayoutColumn(): void {
		$schema = $this->getConnection()->createSchema();
		$table = $schema->getTable($this->getConnection()->getPrefix() . 'tables_views');

		$this->assertTrue($table->hasColumn('layout'));
		$this->assertSame(16, $table->getColumn('layout')->getLength());
	}

	public function testViewUpdateInputAcceptsValidLayout(): void {
		$input = ViewUpdateInput::fromInputArray(['title' => 'Layout view', 'layout' => 'tiles']);
		$updates = iterator_to_array($input->updateDetail());

		$this->assertSame('tiles', $updates[ViewUpdatableParameters::LAYOUT]);
	}

	public function testViewUpdateInputDefaultsMissingOrTableLayoutToNull(): void {
		$missing = iterator_to_array(ViewUpdateInput::fromInputArray(['title' => 'Missing layout'])->updateDetail());
		$table = iterator_to_array(ViewUpdateInput::fromInputArray(['title' => 'Table layout', 'layout' => 'table'])->updateDetail());

		$this->assertArrayNotHasKey(ViewUpdatableParameters::LAYOUT, $missing);
		$this->assertArrayNotHasKey(ViewUpdatableParameters::LAYOUT, $table);
	}

	public function testViewUpdateInputRejectsInvalidLayout(): void {
		$this->expectException(InvalidArgumentException::class);
		ViewUpdateInput::fromInputArray(['title' => 'Invalid layout', 'layout' => 'masonry']);
	}

	public function testViewSerializationNormalizesLayout(): void {
		$defaultView = new View();
		$defaultView->setTitle('Default');
		$defaultView->setColumns('[]');
		$defaultView->setSort('[]');
		$defaultView->setFilter('[]');
		$defaultView->setLayout(null);

		$galleryView = new View();
		$galleryView->setTitle('Gallery');
		$galleryView->setColumns('[]');
		$galleryView->setSort('[]');
		$galleryView->setFilter('[]');
		$galleryView->setLayout('gallery');

		$this->assertSame('table', $defaultView->jsonSerialize()['layout']);
		$this->assertSame('gallery', $galleryView->jsonSerialize()['layout']);
	}
}
