<?php

namespace OCA\Tables\AppInfo;

use OCA\Tables\Listener\UserDeletedListener;
use OCA\Tables\Listener\AnalyticsDatasourceListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

use OCP\User\Events\BeforeUserDeletedEvent;
use OCA\Analytics\Datasource\DatasourceEvent;

class Application extends App implements IBootstrap
{
    public const APP_ID = 'tables';

    public function __construct()
    {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void
    {
        $context->registerEventListener(BeforeUserDeletedEvent::class, UserDeletedListener::class);
        $context->registerEventListener(DatasourceEvent::class, AnalyticsDatasourceListener::class);
    }

    public function boot(IBootContext $context): void
    {
    }
}