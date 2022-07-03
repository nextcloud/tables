<?php

namespace OCA\Tables\Listener;

use OCA\Tables\Db\Table;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Service\TableService;
use OCP\EventDispatcher\IEventListener;
use OCP\EventDispatcher\Event;
use OCP\User\Events\BeforeUserDeletedEvent;
use Psr\Log\LoggerInterface;

class UserDeletedListener implements IEventListener {

    /** @var TableService */
    private $tableService;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(TableService $tableService, LoggerInterface $logger) {
        $this->tableService = $tableService;
        $this->logger = $logger;
    }

    public function handle(Event $event): void {
        if (!($event instanceOf BeforeUserDeletedEvent)) {
            return;
        }

        try {
            $tables = $this->tableService->findAll($event->getUser()->getUID());
            $this->logger->info('event "user deleted" was triggered, will try to delete all data for the user: '.$event->getUser()->getUID().' ('.$event->getUser()->getDisplayName().')');

            // delete tables
            $this->logger->debug('found '.count($tables).' tables for the user');
            foreach ($tables as $table) {
                /** @var $table Table */
                $this->tableService->delete($table->getId(), $event->getUser()->getUID());
            }
            $this->logger->debug('tables for the deleted user removed');

        } catch (InternalError $e) {
        }
    }
}