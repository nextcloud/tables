<?php

namespace OCA\Tables\Helper;

use OCP\IGroupManager;
use Psr\Log\LoggerInterface;

class GroupHelper {
	private LoggerInterface $logger;
	private IGroupManager $groupManager;

	public function __construct(LoggerInterface $logger, IGroupManager $groupManager) {
		$this->logger = $logger;
		$this->groupManager = $groupManager;
	}

	public function getGroupDisplayName(string $groupId): string {
		if ($group = $this->groupManager->get($groupId)) {
			return $group->getDisplayName() ?: $groupId;
		} else {
			$this->logger->info('no group given, will return groupId');
			return $groupId;
		}
	}
}
