<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\AppInfo;

use Exception;
use OCA\Analytics\Datasource\DatasourceEvent;
use OCA\Tables\Capabilities;
use OCA\Tables\Event\RowDeletedEvent;
use OCA\Tables\Event\TableDeletedEvent;
use OCA\Tables\Event\TableOwnershipTransferredEvent;
use OCA\Tables\Event\ViewDeletedEvent;
use OCA\Tables\Listener\AddMissingIndicesListener;
use OCA\Tables\Listener\AnalyticsDatasourceListener;
use OCA\Tables\Listener\BeforeTemplateRenderedListener;
use OCA\Tables\Listener\LoadAdditionalListener;
use OCA\Tables\Listener\TablesReferenceListener;
use OCA\Tables\Listener\UserDeletedListener;
use OCA\Tables\Listener\WhenRowDeletedAuditLogListener;
use OCA\Tables\Listener\WhenTableDeletedAuditLogListener;
use OCA\Tables\Listener\WhenTableTransferredAuditLogListener;
use OCA\Tables\Listener\WhenViewDeletedAuditLogListener;
use OCA\Tables\Middleware\PermissionMiddleware;
use OCA\Tables\Reference\ContentReferenceProvider;
use OCA\Tables\Reference\ReferenceProvider;
use OCA\Tables\Search\SearchTablesProvider;
use OCA\Tables\Service\Support\AuditLogServiceInterface;
use OCA\Tables\Service\Support\DefaultAuditLogService;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\IAppContainer;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\Collaboration\Resources\LoadAdditionalScriptsEvent;
use OCP\DB\Events\AddMissingIndicesEvent;
use OCP\User\Events\BeforeUserDeletedEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'tables';

	public const NODE_TYPE_TABLE = 0;
	public const NODE_TYPE_VIEW = 1;

	public const OWNER_TYPE_USER = 0;

	public const NAV_ENTRY_MODE_HIDDEN = 0;
	public const NAV_ENTRY_MODE_RECIPIENTS = 1;
	public const NAV_ENTRY_MODE_ALL = 2;

	public const PERMISSION_READ = 1;
	public const PERMISSION_CREATE = 2;
	public const PERMISSION_UPDATE = 4;
	public const PERMISSION_DELETE = 8;
	public const PERMISSION_MANAGE = 16;
	public const PERMISSION_ALL = 31;

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

		$context->registerService(AuditLogServiceInterface::class, fn (IAppContainer $c) => $c->query(DefaultAuditLogService::class));

		$context->registerEventListener(BeforeUserDeletedEvent::class, UserDeletedListener::class);
		$context->registerEventListener(DatasourceEvent::class, AnalyticsDatasourceListener::class);
		$context->registerEventListener(RenderReferenceEvent::class, TablesReferenceListener::class);
		$context->registerEventListener(BeforeTemplateRenderedEvent::class, BeforeTemplateRenderedListener::class);
		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadAdditionalListener::class);
		$context->registerEventListener(TableDeletedEvent::class, WhenTableDeletedAuditLogListener::class);
		$context->registerEventListener(ViewDeletedEvent::class, WhenViewDeletedAuditLogListener::class);
		$context->registerEventListener(RowDeletedEvent::class, WhenRowDeletedAuditLogListener::class);
		$context->registerEventListener(TableOwnershipTransferredEvent::class, WhenTableTransferredAuditLogListener::class);
		$context->registerEventListener(AddMissingIndicesEvent::class, AddMissingIndicesListener::class);

		$context->registerSearchProvider(SearchTablesProvider::class);

		$context->registerReferenceProvider(ReferenceProvider::class);
		$context->registerReferenceProvider(ContentReferenceProvider::class);

		$context->registerCapability(Capabilities::class);

		$context->registerMiddleware(PermissionMiddleware::class);
	}

	public function boot(IBootContext $context): void {
	}
}
