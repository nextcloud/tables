<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Controller;

use OCA\Tables\Controller\Api1Controller;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class RelationOpenApiTest extends TestCase {
	public function testRelationEndpointsHaveDefaultOpenApiScope(): void {
		foreach (['indexTableRelations', 'indexViewRelations'] as $method) {
			$attributes = (new ReflectionMethod(Api1Controller::class, $method))->getAttributes(OpenAPI::class);
			$this->assertCount(1, $attributes);
			$this->assertSame(['scope' => OpenAPI::SCOPE_DEFAULT], $attributes[0]->getArguments());
		}
	}

	public function testGeneratedRelationEndpointsPreserveTheExistingResponse(): void {
		$spec = json_decode(file_get_contents(__DIR__ . '/../../../openapi.json'), true, 512, JSON_THROW_ON_ERROR);
		foreach (['tables/{tableId}', 'views/{viewId}'] as $resource) {
			$operation = $spec['paths']['/index.php/apps/tables/api/1/' . $resource . '/relations']['get'];
			$schema = $operation['responses']['200']['content']['application/json']['schema'];
			$properties = $schema['additionalProperties']['additionalProperties']['properties'];
			$this->assertSame('integer', $properties['id']['type']);
			$this->assertSame('string', $properties['label']['type']);
			$this->assertArrayNotHasKey('value', $properties);
			foreach ([401, 403, 404, 500] as $status) {
				$this->assertArrayHasKey($status, $operation['responses']);
			}
		}
	}

	public function testGeneratedCustomSettingsDescribeExistingRelationFields(): void {
		$spec = json_decode(file_get_contents(__DIR__ . '/../../../openapi.json'), true, 512, JSON_THROW_ON_ERROR);
		foreach (['Column', 'PublicColumn'] as $columnType) {
			$settings = $spec['components']['schemas'][$columnType]['properties']['customSettings'];
			$this->assertNotContains('width', $settings['required'] ?? []);
			$this->assertSame(['table', 'view'], $settings['properties']['relationType']['enum']);
			$this->assertSame('integer', $settings['properties']['targetId']['type']);
			$this->assertSame('integer', $settings['properties']['labelColumn']['type']);
			$this->assertArrayNotHasKey('relationColumnId', $settings['properties']);
			$this->assertArrayNotHasKey('targetColumnId', $settings['properties']);
		}
	}
}
