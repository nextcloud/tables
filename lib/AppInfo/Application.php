<?php

namespace OCA\Tables\AppInfo;

use Exception;
use OCA\Analytics\Datasource\DatasourceEvent;
use OCA\Tables\Capabilities;
use OCA\Tables\Listener\AnalyticsDatasourceListener;
use OCA\Tables\Listener\TablesReferenceListener;
use OCA\Tables\Listener\UserDeletedListener;
use OCA\Tables\Reference\RowReferenceProvider;
use OCA\Tables\Reference\SearchableTableReferenceProvider;
use OCA\Tables\Reference\AdvancedTableReferenceProvider;
use OCA\Tables\Reference\TableContentReferenceProvider;
use OCA\Tables\Reference\TableReferenceProvider;
use OCA\Tables\Search\SearchTablesProvider;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\IConfig;
use OCP\Server;
use OCP\User\Events\BeforeUserDeletedEvent;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class Application extends App implements IBootstrap {
	public const APP_ID = 'tables';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	/**
	 * @throws Exception
	 */
	public function register(IRegistrationContext $context): void {
		if ((@include_once __DIR__ . '/../../vendor/autoload.php') === false) {
			throw new Exception('Cannot include autoload. Did you run install dependencies using composer?');
		}

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
				$context->registerReferenceProvider(AdvancedTableReferenceProvider::class);
				$context->registerReferenceProvider(TableContentReferenceProvider::class);
				$context->registerReferenceProvider(RowReferenceProvider::class);
			}
		} catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
		}

		$context->registerCapability(Capabilities::class);
	}

	public function boot(IBootContext $context): void {
	}
}
