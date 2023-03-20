<?php

namespace OCA\Tables\AppInfo;

use OCP\Server;
use OCA\Tables\Listener\UserDeletedListener;
use OCA\Tables\Listener\AnalyticsDatasourceListener;
use OCA\Tables\Listener\TablesReferenceListener;
use OCA\Tables\Reference\SearchableTableReferenceProvider;
use OCA\Tables\Reference\TableReferenceProvider;
use OCA\Tables\Search\SearchTablesProvider;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\IConfig;
use OCP\User\Events\BeforeUserDeletedEvent;
use OCA\Analytics\Datasource\DatasourceEvent;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class Application extends App implements IBootstrap {
	public const APP_ID = 'tables';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(BeforeUserDeletedEvent::class, UserDeletedListener::class);
		$context->registerEventListener(DatasourceEvent::class, AnalyticsDatasourceListener::class);
		$context->registerEventListener(RenderReferenceEvent::class, TablesReferenceListener::class);

		$context->registerSearchProvider(SearchTablesProvider::class);

		try {
			/** @var IConfig $config */
			$config = Server::get(IConfig::class);
			if (version_compare($config->getSystemValueString('version', '0.0.0'), '26.0.0', '<')) {
				$context->registerReferenceProvider(TableReferenceProvider::class);
			} else {
				$context->registerReferenceProvider(SearchableTableReferenceProvider::class);
			}
		} catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
		}
	}

	public function boot(IBootContext $context): void {
	}
}
