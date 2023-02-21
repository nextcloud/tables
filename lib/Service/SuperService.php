<?php

namespace OCA\Tables\Service;

use Psr\Log\LoggerInterface;

class SuperService {
	protected PermissionsService $permissionsService;

	protected LoggerInterface $logger;

	protected ?string $userId;

	public function __construct(LoggerInterface $logger, ?string $userId, PermissionsService $permissionsService) {
		$this->permissionsService = $permissionsService;
		$this->logger = $logger;
		$this->userId = $userId;
	}
}
