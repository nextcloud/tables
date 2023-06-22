<?php

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;

class TextLinkBusiness extends SuperBusiness implements IColumnTypeBusiness {

	public function parseValue(string $value, ?Column $column = null): string {
		// if is import from export in format "[description] ([link])"
		preg_match('/(.*) \((http.*)\)/', $value, $matches);
		if ($matches[0] && $matches[1]) {
			return json_encode(json_encode([
				'title' => $matches[1],
				'resourceUrl' => $matches[2]
			]));
		}

		preg_match('/(http|https)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/', $value, $matches);
		return json_encode(json_encode([
			'title' => $matches[0],
			'resourceUrl' => $matches[0]
		]));
	}
}
