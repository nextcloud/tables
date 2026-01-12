<?php

declare(strict_types=1);

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\Row2;
use PHPUnit\Framework\TestCase;

class Row2Test extends TestCase {
	public function testJsonSerializeMergesCellMetadata(): void {
		$row = new Row2();
		$row->setTableId(1);

		$row->setData([
			['columnId' => 57, 'value' => 'foo'],
			['columnId' => 58, 'value' => 'bar'],
		]);

		$json = $row->jsonSerialize();
		$this->assertArrayNotHasKey('columnName', $json['data'][0]);

		$row->addCellMeta(57, ['columnName' => 'Title 57']);
		$resp = $row->jsonSerialize();
		$this->assertArrayHasKey('columnName', $resp['data'][0]);
		$this->assertSame('Title 57', $resp['data'][0]['columnName']);
	}
}
