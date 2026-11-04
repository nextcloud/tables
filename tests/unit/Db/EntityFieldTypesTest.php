<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\Context;
use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Db\ContextNodeRelation;
use OCA\Tables\Db\ContextNodeRelationMapper;
use OCA\Tables\Db\MenuItemMapper;
use OCA\Tables\Db\PageContent;
use OCA\Tables\Db\PageContentMapper;
use OCA\Tables\Helper\GroupHelper;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * Runs against the database: the integer columns of applications, their node relations
 * and page contents are typed by property name, so they are written and read as integers.
 */
class EntityFieldTypesTest extends DatabaseTestCase {
	/**
	 * @return array<string, array{class-string<Entity>, list<string>}>
	 */
	public static function integerProperties(): array {
		return [
			'application' => [Context::class, ['ownerType']],
			'node relation' => [ContextNodeRelation::class, ['contextId', 'nodeId', 'nodeType', 'permissions']],
			'page content' => [PageContent::class, ['pageId', 'nodeRelId', 'order']],
		];
	}

	/**
	 * @dataProvider integerProperties
	 * @param class-string<Entity> $class
	 * @param list<string> $properties
	 */
	public function testEveryFieldTypeNamesAProperty(string $class, array $properties): void {
		$types = (new $class())->getFieldTypes();

		$this->assertSame([], array_values(array_filter(array_keys($types), static fn (string $field): bool => !property_exists($class, $field))));
		foreach ($properties as $property) {
			$this->assertSame('integer', $types[$property] ?? null, $property);
		}
	}

	/**
	 * @dataProvider integerProperties
	 * @param class-string<Entity> $class
	 * @param list<string> $properties
	 */
	public function testTheMapperBindsTheIntegerColumnsAsIntegers(string $class, array $properties): void {
		$mapper = $this->mapperFor($class);
		$parameterType = new \ReflectionMethod(QBMapper::class, 'getParameterTypeForProperty');

		foreach ($properties as $property) {
			$this->assertSame(IQueryBuilder::PARAM_INT, $parameterType->invoke($mapper, new $class(), $property), $property);
		}
	}

	public function testANodeRelationAndAPageContentAreReadBackAsIntegers(): void {
		$relationMapper = new ContextNodeRelationMapper($this->connectionAdapter);
		$contentMapper = new PageContentMapper($this->connectionAdapter);
		$relation = new ContextNodeRelation();
		$relation->setContextId(990701);
		$relation->setNodeId(990702);
		$relation->setNodeType(1);
		$relation->setPermissions(3);
		$relation = $relationMapper->insert($relation);
		$content = new PageContent();
		$content->setPageId(990703);
		$content->setNodeRelId($relation->getId());
		$content->setOrder(20);
		$content = $contentMapper->insert($content);

		try {
			$readRelation = $relationMapper->findById($relation->getId());
			$readContent = $contentMapper->findById($content->getId());

			$this->assertSame([990701, 990702, 1, 3], [$readRelation->getContextId(), $readRelation->getNodeId(), $readRelation->getNodeType(), $readRelation->getPermissions()]);
			$this->assertSame([990703, $relation->getId(), 20], [$readContent->getPageId(), $readContent->getNodeRelId(), $readContent->getOrder()]);
		} finally {
			$contentMapper->delete($content);
			$relationMapper->delete($relation);
		}
	}

	/**
	 * @param class-string<Entity> $class
	 */
	private function mapperFor(string $class): QBMapper {
		return match ($class) {
			Context::class => new ContextMapper($this->connectionAdapter, $this->createMock(UserHelper::class), $this->createMock(GroupHelper::class), new MenuItemMapper($this->connectionAdapter)),
			ContextNodeRelation::class => new ContextNodeRelationMapper($this->connectionAdapter),
			PageContent::class => new PageContentMapper($this->connectionAdapter),
		};
	}
}
