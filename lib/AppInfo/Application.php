<?php

namespace OCA\Tables\AppInfo;

use OCA\Tables\Listener\UserDeletedListener;
use OCA\Tables\Listener\AnalyticsDatasourceListener;
use OCA\Tables\Listener\TablesReferenceListener;
use OCA\Tables\Reference\TableReferenceProvider;
use OCA\Tables\Search\SearchTablesProvider;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\User\Events\BeforeUserDeletedEvent;
use OCA\Analytics\Datasource\DatasourceEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'tables';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(BeforeUserDeletedEvent::class, UserDeletedListener::class);
		$context->registerEventListener(DatasourceEvent::class, AnalyticsDatasourceListener::class);
		$context->registerSearchProvider(SearchTablesProvider::class);
		$context->registerReferenceProvider(TableReferenceProvider::class);
		$context->registerEventListener(RenderReferenceEvent::class, TablesReferenceListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}
