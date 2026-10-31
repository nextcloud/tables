<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Federation;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\AuditLog\Operation;
use OCA\Tables\Constants\ShareReceiverType;
use OCA\Tables\Constants\ViewLayout;
use OCA\Tables\Db\Share;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\FederationDisabledError;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Service\ArchiveService;
use OCA\Tables\Service\FederationService;
use OCA\Tables\Service\Support\AuditLogServiceInterface;
use OCA\Tables\Service\ValueObject\ShareToken;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Federation\Exceptions\BadRequestException;
use OCP\Federation\Exceptions\ProviderCouldNotAddShareException;
use OCP\Federation\ICloudFederationShare;
use OCP\Federation\ICloudIdManager;
use OCP\Federation\ISignedCloudFederationProvider;
use OCP\Share\Exceptions\ShareNotFound;

class FederationProvider implements ISignedCloudFederationProvider {
	public const PROVIDER_ID = 'tables';
	public const SHARE_TYPE_USER = 'user';
	public const NOTIFICATION_UPDATE_PERMISSIONS = 'update-permissions';
	public const NOTIFICATION_DELETE_NODE = 'delete-node';
	public const NOTIFICATION_UPDATE_NODE = 'update-node';

	public function __construct(
		private readonly TableMapper $tableMapper,
		private readonly ViewMapper $viewMapper,
		private readonly ShareMapper $shareMapper,
		private readonly ICloudIdManager $cloudIdManager,
		private readonly FederationService $federationService,
		private readonly AuditLogServiceInterface $auditLogService,
		private readonly ArchiveService $archiveService,
	) {
	}

	public function getShareType(): string {
		return self::PROVIDER_ID;
	}

	public function getFederationIdFromSharedSecret(string $sharedSecret, array $payload): string {
		try {
			$share = $this->shareMapper->findByToken(new ShareToken($sharedSecret));
		} catch (DoesNotExistException) {
			return '';
		}

		return $share->getSender();
	}

	/**
	 * @throws ProviderCouldNotAddShareException
	 * @throws InternalError
	 */
	public function shareReceived(ICloudFederationShare $share): string {
		try {
			$this->federationService->ensureIncomingFederationEnabled();
		} catch (FederationDisabledError $e) {
			throw new ProviderCouldNotAddShareException($e->getMessage());
		}

		$localUser = $share->getShareWith();
		if (str_contains($localUser, '@')) {
			$localUser = $this->cloudIdManager->resolveCloudId($localUser)->getUser();
		}

		$metaData = json_decode($share->getDescription(), true) ?? [];
		if (!isset($metaData['nodeType'])) {
			throw new ProviderCouldNotAddShareException('Missing node type in share description');
		}

		$nodeType = $metaData['nodeType'];
		if (!in_array($nodeType, ['table', 'view'], true)) {
			throw new ProviderCouldNotAddShareException('Unsupported node type: ' . $nodeType);
		}

		$nodeId = $this->insertFederatedNode($share, $metaData, $nodeType);
		$localShare = $this->buildShareForFederationNode($share, $nodeId, $nodeType, $localUser);

		try {
			$localShare = $this->shareMapper->insert($localShare);
		} catch (\Exception $e) {
			throw new ProviderCouldNotAddShareException('Could not create share for federated share: ' . $e->getMessage());
		}
		$this->auditLogService->log(Operation::FederatedShareReceived, 'Federated share %s of %s %s from "%s" was received for user "%s"', [
			'shareId' => $localShare->getId(),
			'nodeType' => $nodeType,
			'nodeId' => $nodeId,
			'sharedBy' => $share->getSharedBy(),
			'receiver' => $localUser,
		]);

		return (string)$nodeId;
	}

	public function notificationReceived($notificationType, $providerId, $notification): array {
		match($notificationType) {
			self::NOTIFICATION_UPDATE_PERMISSIONS => $this->handlePermissionUpdate($notification),
			self::NOTIFICATION_DELETE_NODE => $this->handleNodeDelete($providerId, $notification),
			self::NOTIFICATION_UPDATE_NODE => $this->handleNodeUpdate($providerId, $notification),
			default => throw new BadRequestException(['nodeType']),
		};

		return [];
	}

	public function getSupportedShareTypes(): array {
		return ['user'];
	}

	private function getMapperForNodeType(string $nodeType): TableMapper|ViewMapper {
		return match($nodeType) {
			'table' => $this->tableMapper,
			'view' => $this->viewMapper,
			default => throw new ProviderCouldNotAddShareException('Unsupported node type: ' . $nodeType),
		};
	}

	private function insertFederatedNode(ICloudFederationShare $share, array $metaData, string $nodeType): int {
		$node = $this->buildNodeForFederationShare($share, $metaData, $nodeType);

		try {
			return $this->getMapperForNodeType($nodeType)->insert($node)->getId();
		} catch (\Exception $e) {
			throw new ProviderCouldNotAddShareException('Could not add federated ' . $nodeType . ': ' . $e->getMessage());
		}
	}

	private function buildNodeForFederationShare(ICloudFederationShare $share, array $metaData, string $nodeType): Table|View {
		$node = match($nodeType) {
			'table' => new Table(),
			'view' => new View(),
			default => throw new ProviderCouldNotAddShareException('Unsupported node type: ' . $nodeType),
		};
		$now = (new \DateTime())->format('Y-m-d H:i:s');

		$node->setTitle($share->getResourceName());
		$node->setExternalId((int)$share->getProviderId());
		$node->setOwnership($share->getOwner());
		$node->setShareToken($share->getShareSecret());
		$node->setCreatedBy($share->getSharedBy());
		$node->setCreatedAt($now);
		$node->setLastEditBy($share->getSharedBy());
		$node->setLastEditAt($now);
		$node->setEmoji($metaData['emoji'] ?? null);

		if ($node instanceof View) {
			$node->setDescription('');
			$node->setLayout(self::layoutFromMetaData($metaData));
		}

		if (property_exists($node, 'uuid')) {
			$node->setUuid($metaData['uuid'] ?? null);
		}

		return $node;
	}

	private function buildShareForFederationNode(ICloudFederationShare $share, int $nodeId, string $nodeType, string $localUser): Share {
		$now = (new \DateTime())->format('Y-m-d H:i:s');

		$localShare = new Share();
		$localShare->setSender($share->getOwner());
		$localShare->setReceiver($localUser);
		$localShare->setReceiverType(ShareReceiverType::USER);
		$localShare->setNodeId($nodeId);
		$localShare->setNodeType($nodeType);
		$localShare->setToken($share->getShareSecret());
		$localShare->setPermissionRead(true);
		$localShare->setPermissionCreate(false);
		$localShare->setPermissionUpdate(false);
		$localShare->setPermissionDelete(false);
		$localShare->setPermissionManage(false);
		$localShare->setCreatedAt($now);
		$localShare->setLastEditAt($now);

		return $localShare;
	}

	private function findNodeByExternalIdAndToken(string $providerId, array $notification, string $nodeType): Table|View {
		$node = $this->getMapperForNodeType($nodeType)->findByExternalIdAndToken((int)$providerId, $notification['sharedSecret']);
		if ($node === null) {
			throw new ShareNotFound('No matching federated ' . $nodeType . ' found');
		}

		return $node;
	}

	private function handlePermissionUpdate(array $notification): void {
		try {
			$share = $this->shareMapper->findByToken(new ShareToken($notification['sharedSecret']));
		} catch (DoesNotExistException) {
			throw new ShareNotFound('No share found for token');
		}

		$share->setPermissionRead($notification['permissionRead']);
		$share->setPermissionCreate($notification['permissionCreate']);
		$share->setPermissionUpdate($notification['permissionUpdate']);
		$share->setPermissionDelete($notification['permissionDelete']);
		$this->shareMapper->update($share);
		$this->auditLogService->log(Operation::FederatedSharePermissionsUpdated, 'Permissions of federated share %s of %s %s for "%s" were changed by the sharing server', [
			'shareId' => $share->getId(),
			'nodeType' => $share->getNodeType(),
			'nodeId' => $share->getNodeId(),
			'receiver' => $share->getReceiver(),
			'permissions' => [
				'read' => $share->getPermissionRead(),
				'create' => $share->getPermissionCreate(),
				'update' => $share->getPermissionUpdate(),
				'delete' => $share->getPermissionDelete(),
			],
		]);
	}

	private function handleNodeDelete(string $providerId, array $notification): void {
		if (!isset($notification['nodeType'])) {
			throw new BadRequestException(['nodeType']);
		}

		$nodeType = $notification['nodeType'];
		$node = $this->findNodeByExternalIdAndToken($providerId, $notification, $nodeType);
		$this->shareMapper->deleteByNode($node->getId(), $nodeType);
		if ($nodeType === 'table') {
			// The share rows go first, so no later event could reach these.
			$this->archiveService->deleteNodeArchiveOverrides(Application::NODE_TYPE_TABLE, $node->getId());
		}
		$this->getMapperForNodeType($nodeType)->delete($node);
	}

	private function handleNodeUpdate(string $providerId, array $notification): void {
		if (!isset($notification['nodeType'])) {
			throw new BadRequestException(['nodeType']);
		}

		$nodeType = $notification['nodeType'];
		$node = $this->findNodeByExternalIdAndToken($providerId, $notification, $nodeType);
		if (isset($notification['title'])) {
			$node->setTitle($notification['title']);
		}
		if (isset($notification['emoji'])) {
			$node->setEmoji($notification['emoji']);
		}
		if ($node instanceof View && isset($notification['layout'])) {
			$layout = self::layoutFromMetaData($notification);
			if ($layout !== null) {
				$node->setLayout($layout);
			}
		}
		$this->getMapperForNodeType($nodeType)->update($node);
	}

	/**
	 * The layout a remote instance named, or null when this version does not know it.
	 *
	 * The value comes from another server, so it is only accepted as one of the layouts
	 * this version defines.
	 */
	private static function layoutFromMetaData(array $data): ?string {
		return ViewLayout::tryFromMixed($data['layout'] ?? null)?->value;
	}
}
