<?php

namespace OCA\Tables\Helper;

use OCA\Tables\Errors\InternalError;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class UserHelper {

    /** @var IUserManager @var IUserManager */
    private $userManager;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(IUserManager $userManager, LoggerInterface $logger) {
        $this->userManager = $userManager;
        $this->logger = $logger;
    }
    public function getUserDisplayName($userId): string
    {
        try {
            $user = $this->getUser($userId);
            return $user->getDisplayName() ? $user->getDisplayName() : $userId;
        } catch (InternalError $e) {
            $this->logger->info('no user given, will return userId');
            return $userId;
        }
    }

    /**
     * @throws InternalError
     */
    private function getUser(string $userId): IUser {
        $user = $this->userManager->get($userId);
        if ($user instanceof IUser) {
            return $user;
        }
        throw new InternalError('User not found for '.$userId);
    }

}
