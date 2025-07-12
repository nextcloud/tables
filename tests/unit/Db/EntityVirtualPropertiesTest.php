<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\EntitySuper;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;
use OCP\AppFramework\Db\Entity;
use ReflectionClass;
use ReflectionProperty;

/**
 * Test class for checking Entity virtual properties consistency
 */
class EntityVirtualPropertiesTest extends DatabaseTestCase {
	private array $systemProperties = ['_updatedFields', '_fieldTypes'];

	/**
	 * Test that all Entity classes have proper VIRTUAL_PROPERTIES defined
	 * for properties that don't exist in database tables
	 *
	 * @dataProvider entityClassesProvider
	 * @param string $className Fully qualified class name
	 */
	public function testEntityVirtualPropertiesConsistency(string $className): void {
		$this->checkEntityClass($className);
	}

	/**
	 * Data provider for Entity classes
	 *
	 * @return array<string, array<string>> Array of test cases with class names
	 */
	public function entityClassesProvider(): array {
		$entityClasses = $this->getEntityClasses();
		$testCases = [];

		foreach ($entityClasses as $className) {
			$shortClassName = basename(str_replace('\\', '/', $className));
			$testCases[$shortClassName] = [$className];
		}

		return $testCases;
	}

	/**
	 * Get all Entity classes from lib/Db directory
	 *
	 * @return array<string> Array of fully qualified class names
	 */
	private function getEntityClasses(): array {
		$entityClasses = [];
		$dbDir = __DIR__ . '/../../../lib/Db';

		// Get all PHP files in lib/Db directory
		$files = glob($dbDir . '/*.php');

		foreach ($files as $file) {
			$className = $this->getClassNameFromFile($file);
			if ($className && $this->isEntityClass($className)) {
				$entityClasses[] = $className;
			}
		}

		return $entityClasses;
	}

	/**
	 * Extract class name from PHP file
	 *
	 * @param string $filePath Path to PHP file
	 * @return string|null Fully qualified class name or null if not found
	 */
	private function getClassNameFromFile(string $filePath): ?string {
		$content = file_get_contents($filePath);
		if (!$content) {
			return null;
		}

		// Check if file contains class definition
		if (!preg_match('/class\s+(\w+)/', $content, $matches)) {
			return null;
		}

		$className = $matches[1];

		// Extract namespace
		if (preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatches)) {
			$namespace = trim($namespaceMatches[1]);
			return $namespace . '\\' . $className;
		}

		return null;
	}

	/**
	 * Check if class extends Entity or EntitySuper
	 *
	 * @param string $className Fully qualified class name
	 * @return bool True if class extends Entity or EntitySuper
	 */
	private function isEntityClass(string $className): bool {
		try {
			$reflection = new ReflectionClass($className);

			// Перевірити, що клас не абстрактний
			if ($reflection->isAbstract()) {
				return false;
			}

			// Check if class extends Entity or EntitySuper
			$parentClass = $reflection->getParentClass();
			while ($parentClass) {
				if ($parentClass->getName() === Entity::class
					|| $parentClass->getName() === EntitySuper::class) {
					return true;
				}
				$parentClass = $parentClass->getParentClass();
			}
		} catch (\ReflectionException $e) {
			// Class doesn't exist or can't be loaded
			return false;
		}

		return false;
	}

	/**
	 * Check individual Entity class for virtual properties consistency
	 *
	 * @param string $className Fully qualified class name
	 */
	private function checkEntityClass(string $className): void {
		$reflection = new ReflectionClass($className);

		// 2. Get all properties from the class
		$classProperties = $this->getClassProperties($reflection);

		// 3. Get database table fields
		$tableFieldsRaw = $this->getDatabaseTableFields($className);
		$tableFields = [];
		foreach ($tableFieldsRaw as $columnName) {
			$tableFields[] = $this->columnToProperty($columnName);
		}

		// 4. Get VIRTUAL_PROPERTIES constant
		$virtualProperties = $this->getVirtualProperties($reflection);

		// 5. Check that all properties not in database are in VIRTUAL_PROPERTIES
		$missingVirtualProperties = [];

		foreach ($classProperties as $property) {
			// Skip properties that exist in database
			if (in_array($property, $tableFields)) {
				continue;
			}

			// Check if property is in VIRTUAL_PROPERTIES
			if (!in_array($property, $virtualProperties)) {
				$missingVirtualProperties[] = $property;
			}
		}

		// Assert that all missing properties are in VIRTUAL_PROPERTIES
		$this->assertEmpty(
			$missingVirtualProperties,
			sprintf(
				'Class %s has properties that are not in database but not marked as VIRTUAL_PROPERTIES: %s',
				$className,
				implode(', ', $missingVirtualProperties)
			)
		);
	}

	/**
	 * Get all properties from Entity class
	 *
	 * @param ReflectionClass $reflection Class reflection
	 * @return array<string> Array of property names
	 */
	private function getClassProperties(ReflectionClass $reflection): array {
		$properties = [];

		// Get all properties from this class and parent classes
		$currentClass = $reflection;
		while ($currentClass) {
			foreach ($currentClass->getProperties(ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE) as $property) {
				$propertyName = $property->getName();

				// Skip properties that are not meant to be database fields
				if (in_array($propertyName, $this->systemProperties)) {
					continue;
				}

				$properties[] = $propertyName;
			}
			$currentClass = $currentClass->getParentClass();
		}

		return array_unique($properties);
	}

	/**
	 * Get database table fields for Entity class
	 *
	 * @param string $className Fully qualified class name
	 * @return array<string> Array of database field names
	 */
	private function getDatabaseTableFields(string $className): array {
		try {
			// Try to get table name from class
			$tableName = $this->getTableNameFromClass($className);
			if (!$tableName) {
				return [];
			}

			// Get table schema
			$schema = $this->connection->createSchema();
			$fullTableName = $this->connection->getPrefix() . $tableName;

			if (!$schema->hasTable($fullTableName)) {
				return [];
			}

			$table = $schema->getTable($fullTableName);
			$columns = $table->getColumns();

			$fields = [];
			foreach ($columns as $column) {
				$fields[] = $column->getName();
			}

			return $fields;
		} catch (\Exception $e) {
			// If we can't get table fields, return empty array
			return [];
		}
	}

	/**
	 * Get table name from Entity class
	 *
	 * @param string $className Fully qualified class name
	 * @return string|null Table name or null if not found
	 */
	private function getTableNameFromClass(string $className): ?string {
		try {
			$reflection = new ReflectionClass($className);

			// Try to get table name from protected property
			if ($reflection->hasProperty('table')) {
				$tableProperty = $reflection->getProperty('table');
				$tableProperty->setAccessible(true);

				// Create instance to get table name
				$instance = $reflection->newInstanceWithoutConstructor();
				$tableName = $tableProperty->getValue($instance);

				if ($tableName) {
					return $tableName;
				}
			}

			// Try to get table name from mapper class
			$mapperClassName = $className . 'Mapper';
			if (class_exists($mapperClassName)) {
				$mapperReflection = new ReflectionClass($mapperClassName);
				if ($mapperReflection->hasProperty('table')) {
					$tableProperty = $mapperReflection->getProperty('table');
					$tableProperty->setAccessible(true);

					// Create mapper instance to get table name
					$mapperInstance = $mapperReflection->newInstanceWithoutConstructor();
					$tableName = $tableProperty->getValue($mapperInstance);

					if ($tableName) {
						return $tableName;
					}
				}
			}

			// Try to infer table name from class name
			$shortClassName = basename(str_replace('\\', '/', $className));
			$tableName = 'tables_' . strtolower($shortClassName) . 's';

			// Check if table exists
			$schema = $this->connection->createSchema();
			$fullTableName = $this->connection->getPrefix() . $tableName;

			if ($schema->hasTable($fullTableName)) {
				return $tableName;
			}

		} catch (\Exception $e) {
			// If we can't get table name, return null
		}

		return null;
	}

	/**
	 * Get VIRTUAL_PROPERTIES constant from Entity class
	 *
	 * @param ReflectionClass $reflection Class reflection
	 * @return array<string> Array of virtual property names
	 */
	private function getVirtualProperties(ReflectionClass $reflection): array {
		try {
			// Try to get VIRTUAL_PROPERTIES constant from this class
			if ($reflection->hasConstant('VIRTUAL_PROPERTIES')) {
				$virtualProperties = $reflection->getConstant('VIRTUAL_PROPERTIES');
				if (is_array($virtualProperties)) {
					return $virtualProperties;
				}
			}

			// Try to get from parent classes
			$parentClass = $reflection->getParentClass();
			while ($parentClass) {
				if ($parentClass->hasConstant('VIRTUAL_PROPERTIES')) {
					$virtualProperties = $parentClass->getConstant('VIRTUAL_PROPERTIES');
					if (is_array($virtualProperties)) {
						return $virtualProperties;
					}
				}
				$parentClass = $parentClass->getParentClass();
			}
		} catch (\Exception $e) {
			// If we can't get VIRTUAL_PROPERTIES, return empty array
		}

		return [];
	}

	public function columnToProperty(string $columnName) {
		$parts = explode('_', $columnName);
		$property = '';

		foreach ($parts as $part) {
			if ($property === '') {
				$property = $part;
			} else {
				$property .= ucfirst($part);
			}
		}

		return $property;
	}
}
