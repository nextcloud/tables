<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;

class TextLinkBusiness extends SuperBusiness implements IColumnTypeBusiness {

	/**
	 * @param mixed $value (string|null)
	 * @param Column|null $column
	 * @return string
	 */
	public function parseValue($value, ?Column $column = null): string {
		if ($value === null) {
			return '';
		}

		// if is import from export in format "[description] ([link])"
		preg_match('/(.*) \((http.*)\)/', $value, $matches);
		if (!empty($matches) && $matches[0] && $matches[1]) {
			return json_encode(json_encode([
				'title' => $matches[1],
				'value' => $matches[2],
				'providerId' => 'url',
			]));
		}

		// if is json (this is the default case, other formats are backward compatibility
		$data = json_decode($value, true);
		if ($data !== null) {
			if (isset($data['resourceUrl'])) {
				return json_encode(json_encode([
					'title' => $data['title'] ?? $data['resourceUrl'],
					'value' => $data['resourceUrl'],
					'providerId' => $data['providerId'] ?? 'url',
				]));
			}
			// at least title and resUrl have to be set
			if (isset($data['title']) && isset($data['value'])) {
				return json_encode($value);
			} else {
				$this->logger->warning('Link cell value has incomplete json string.');
				return '';
			}
		}

		// if is just a url (old implementation)
		preg_match('/(http|https)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/', $value, $matches);
		if (empty($matches)) {
			return '';
		}
		return json_encode(json_encode([
			'title' => $matches[0],
			'value' => $matches[0],
			'providerId' => 'url',
		]));
	}

	/**
	 * @param mixed $value (string|null)
	 * @param Column|null $column
	 * @return bool
	 */
	public function canBeParsed($value, ?Column $column = null): bool {
		if (!$value) {
			return true;
		}
		preg_match('/(.*) \((http.*)\)/', $value, $matches);
		if (!empty($matches) && $matches[0] && $matches[1]) {
			return true;
		}

		$data = json_decode($value, true);
		if ($data !== null) {
			if (!isset($data['resourceUrl']) && !isset($data['value'])) {
				$this->logger->error('Value ' . $value . ' cannot be parsed as the column ' . $column->getId() . ' as it contains incomplete data');
				return false;
			}

			// Validate url providers
			$allowedProviders = explode(',', $column->getTextAllowedPattern() ?? '') ?: [];
			if (isset($data['providerId']) && !in_array($data['providerId'], $allowedProviders)) {
				$this->logger->error('Value ' . $value . ' cannot be parsed as the column ' . $column->getId() . ' does not allow the provider: ' . $data['providerId']);
				return false;
			}

			return true;
		}

		preg_match('/(http|https)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/', $value, $matches);
		return !empty($matches);
	}
}
