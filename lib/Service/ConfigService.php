<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\PermissionError;
use OCP\Config\IUserConfig;
use OCP\IUserSession;

class ConfigService {
	private ?string $userId = null;

	public const NOTIFY_COLUMN_KEY = 'notify-column';
	public const NOTIFY_ROW_KEY = 'notify-row';
	public const NOTIFY_ASSIGNED_KEY = 'notify-assigned';

	protected const NOTIFY_CONFIG_KEYS = [
		self::NOTIFY_COLUMN_KEY,
		self::NOTIFY_ROW_KEY,
		self::NOTIFY_ASSIGNED_KEY,
	];

	public function __construct(
		private readonly IUserConfig $userConfig,
	) {
	}

	public function getUserId(): ?string {
		if (!$this->userId) {
			$user = \OCP\Server::get(IUserSession::class)->getUser();
			$this->userId = $user ? $user->getUID() : null;
		}

		return $this->userId;
	}

	/**
	 * @return string|bool
	 */
	public function get(string $key): string|bool {
		$userId = $this->getUserId();
		if ($userId === null) {
			return false;
		}

		[$scope, $configKey] = explode(':', $key, 2);

		switch ($scope) {
			case 'table':
			case 'view':
				[$tableId, $configKey] = explode(':', $configKey, 2);
				if (in_array($configKey, self::NOTIFY_CONFIG_KEYS)) {
					return $this->userConfig->getValueBool($userId, Application::APP_ID, $key);
				}
		}

		return false;
	}

	/**
	 * Set a configuration value for the current user
	 *
	 * @throws BadRequestError
	 * @throws PermissionError
	 */
	public function set(string $key, mixed $value): void {
		$userId = $this->getUserId();
		if ($userId === null) {
			throw new PermissionError('Must be logged in to set user config');
		}

		[$scope, $configKey] = explode(':', $key, 2);
		switch ($scope) {
			case 'table':
			case 'view':
				[$tableId, $configKey] = explode(':', $configKey, 2);
				if (!in_array($configKey, self::NOTIFY_CONFIG_KEYS)) {
					throw new BadRequestError('Unknown configuration key: ' . $configKey);
				}
				if (!is_bool($value)) {
					throw new BadRequestError('Invalid value for ' . $configKey . ' config, must be boolean');
				}
				$this->userConfig->setValueBool($userId, Application::APP_ID, $key, $value);
				break;
			default:
				throw new BadRequestError('Unknown scope: ' . $scope);
		}
	}

	/**
	 * Get all configuration values for a specific table
	 *
	 * @param int $tableId
	 * @return array
	 */
	public function getTableConfig(int $tableId): array {
		$userId = $this->getUserId();
		if ($userId === null) {
			return [];
		}

		$config = [];

		foreach (self::NOTIFY_CONFIG_KEYS as $key) {
			$fullKey = 'table:' . $tableId . ':' . $key;
			$config[$key] = $this->get($fullKey);
		}

		return $config;
	}

	/**
	 * Get all configuration values for a specific view
	 *
	 * @param int $tableId
	 * @return array
	 */
	public function getViewConfig(int $tableId): array {
		$userId = $this->getUserId();
		if ($userId === null) {
			return [];
		}

		$config = [];

		foreach (self::NOTIFY_CONFIG_KEYS as $key) {
			$fullKey = 'view:' . $tableId . ':' . $key;
			$config[$key] = $this->get($fullKey);
		}

		return $config;
	}

	public function isNotifyEnabledForScope(string $userId, string $scope, int $scopeId, string $key): bool {
		if (in_array($key, self::NOTIFY_CONFIG_KEYS)) {
			return $this->userConfig->getValueBool($userId, Application::APP_ID, $scope . ':' . $scopeId . ':' . $key);
		}
		return false;
	}
}
