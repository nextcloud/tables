<?php

declare(strict_types=1);

namespace OCA\Tables\Service;

use OCA\Tables\Db\Context;
use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Errors\InternalError;
use OCP\DB\Exception;
use Psr\Log\LoggerInterface;

class ContextService {

	private ContextMapper $mapper;
	private bool $isCLI;
	private LoggerInterface $logger;

	public function __construct(
		ContextMapper $mapper,
		LoggerInterface $logger,
		bool $isCLI,
	) {
		$this->mapper = $mapper;
		$this->isCLI = $isCLI;
		$this->logger = $logger;
	}

	/**
	 * @throws InternalError
	 * @throws Exception
	 * @return Context[]
	 */
	public function findAll(?string $userId): array {
		if ($userId !== null && trim($userId) === '') {
			$userId = null;
		}
		if ($userId === null && !$this->isCLI) {
			$error = 'Try to set no user in context, but request is not allowed.';
			$this->logger->warning($error);
			throw new InternalError($error);
		}
		return $this->mapper->findAll($userId);
	}
}
