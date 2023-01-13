<?php

namespace OCA\Tables\Service;

use Psr\Log\LoggerInterface;

class SuperService {

	/** @var PermissionsService */
	protected $permissionsService;

	/** @var LoggerInterface */
	protected $logger;

	protected $userId;

	public function __construct(LoggerInterface $logger, $userId, PermissionsService $permissionsService = null) {
		$this->permissionsService = $permissionsService;
		$this->logger = $logger;
		$this->userId = $userId;
	}
}
