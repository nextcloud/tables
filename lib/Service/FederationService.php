<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use OCA\Tables\Db\Share;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\FederationDisabledError;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Federation\FederationProvider;
use OCA\Tables\Federation\FederationProxy;
use OCA\Tables\Helper\UserHelper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Federation\ICloudIdManager;
use OCP\OCM\Exceptions\OCMProviderException;
use Psr\Log\LoggerInterface;

class FederationService {
	public function __construct(
		private FederationProxy $proxy,
		private ICloudIdManager $cloudIdManager,
		private LoggerInterface $logger,
		private ShareMapper $shareMapper,
		private TableMapper $tableMapper,
		private ViewMapper $viewMapper,
		private UserHelper $userHelper,
		private ICloudFederationProviderManager $federationProviderManager,
		private ICloudFederationFactory $federationFactory,
		private ConfigService $configService,
	) {
	}

	private function getRemoteBaseUrl(Table|View $node): string {
		$ownership = $node->getOwnership() ?? $node->getCreatedBy();
		$remote = $this->cloudIdManager->resolveCloudId($ownership)->getRemote();
		return $remote . '/ocs/v2.php/apps/tables/api/2/public/' . $node->getShareToken();
	}

	public function getColumns(Table|View $node): array {
		try {
			$response = $this->proxy->get(
				$node->getShareToken(),
				$this->getRemoteBaseUrl($node) . '/columns',
			);
			return $this->proxy->getOCSData($response);
		} catch (\Exception $e) {
			$this->logger->error('Could not fetch columns from remote node', ['exception' => $e]);
			throw $e;
		}
	}

	public function getRows(Table|View $node, ?int $limit = null, ?int $offset = null): array {
		$url = $this->getRemoteBaseUrl($node) . '/rows';
		$params = array_filter(['limit' => $limit, 'offset' => $offset]);
		try {
			$response = $this->proxy->get(
				$node->getShareToken(),
				$url,
				$params
			);
			return $this->proxy->getOCSData($response);
		} catch (\Exception $e) {
			$this->logger->error('Could not fetch rows from remote node', ['exception' => $e]);
			throw $e;
		}
	}

	public function createRow(Table|View $node, array $data): array {
		try {
			$response = $this->proxy->post(
				$node->getShareToken(),
				$this->getRemoteBaseUrl($node) . '/rows',
				['data' => $data],
			);
			return $this->proxy->getOCSData($response);
		} catch (\Exception $e) {
			$this->logger->error('Could not create row on remote node', ['exception' => $e]);
			throw $e;
		}
	}

	public function updateRow(Table|View $node, int $rowId, array $data): array {
		try {
			$response = $this->proxy->put(
				$node->getShareToken(),
				$this->getRemoteBaseUrl($node) . '/rows/' . $rowId,
				['data' => $data],
			);
			return $this->proxy->getOCSData($response);
		} catch (\Exception $e) {
			$this->logger->error('Could not update row on remote node', ['exception' => $e]);
			throw $e;
		}
	}

	public function deleteRow(Table|View $node, int $rowId): array {
		try {
			$response = $this->proxy->delete(
				$node->getShareToken(),
				$this->getRemoteBaseUrl($node) . '/rows/' . $rowId,
			);
			return $this->proxy->getOCSData($response);
		} catch (\Exception $e) {
			$this->logger->error('Could not delete row on remote node', ['exception' => $e]);
			throw $e;
		}
	}

	/**
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws InternalError
	 * @throws MultipleObjectsReturnedException
	 * @throws FederationDisabledError
	 */
	public function sendShare(Share $share): void {
		$this->ensureOutgoingFederationEnabled();

		$cloudId = $this->cloudIdManager->resolveCloudId($share->getReceiver());
		$ownerCloudId = $this->cloudIdManager->getCloudId($share->getSender(), null);
		$ownerDisplayName = $this->userHelper->getUserDisplayName($share->getSender());

		$node = match($share->getNodeType()) {
			'view' => $this->viewMapper->find($share->getNodeId()),
			'table' => $this->tableMapper->find($share->getNodeId()),
			default => throw new InternalError('Unsupported node type for federation: ' . $share->getNodeType()),
		};

		$federationShare = $this->federationFactory->getCloudFederationShare(
			$cloudId->getId(),
			$node->getTitle(),
			json_encode([
				'emoji' => $node->getEmoji(),
				'nodeType' => $share->getNodeType()
			]),
			(string)$share->getNodeId(),
			$ownerCloudId->getId(),
			$ownerDisplayName,
			$ownerCloudId->getId(),
			$ownerDisplayName,
			$share->getToken(),
			FederationProvider::SHARE_TYPE_USER,
			FederationProvider::PROVIDER_ID,
		);
		try {
			$this->federationProviderManager->sendCloudShare($federationShare);
		} catch (OCMProviderException $e) {
			$this->logger->error('Failed to send federated share: ' . $e->getMessage(), ['exception' => $e]);
			throw new InternalError('Could not send federated share to remote instance');
		}
	}

	public function notifyNodeDelete(Table|View $node, string $nodeType): void {
		try {
			$shares = $this->shareMapper->findRemoteSharesForNode($node->getId(), $nodeType);
		} catch (\Exception $e) {
			$this->logger->warning('Could not fetch remote shares for node deletion notification', ['exception' => $e]);
			return;
		}
		foreach ($shares as $share) {
			$this->proxy->sendNotification(
				FederationProvider::NOTIFICATION_DELETE_NODE,
				(string)$node->getId(),
				$share,
				['nodeType' => $nodeType],
			);
		}
	}

	public function notifyPermissionUpdate(Share $share): void {
		$this->proxy->sendNotification(
			FederationProvider::NOTIFICATION_UPDATE_PERMISSIONS,
			(string)$share->getNodeId(),
			$share,
			[
				'permissionRead' => $share->getPermissionRead(),
				'permissionCreate' => $share->getPermissionCreate(),
				'permissionUpdate' => $share->getPermissionUpdate(),
				'permissionDelete' => $share->getPermissionDelete(),
			]
		);
	}

	public function notifyNodeUpdate(Table|View $node, string $nodeType): void {
		try {
			$shares = $this->shareMapper->findRemoteSharesForNode($node->getId(), $nodeType);
		} catch (\Exception $e) {
			$this->logger->warning('Could not fetch remote shares for node update notification', ['exception' => $e]);
			return;
		}
		foreach ($shares as $share) {
			$this->proxy->sendNotification(
				FederationProvider::NOTIFICATION_UPDATE_NODE,
				(string)$node->getId(),
				$share,
				[
					'title' => $node->getTitle(),
					'emoji' => $node->getEmoji(),
					'nodeType' => $nodeType,
				]
			);
		}
	}

	public function notifyShareDelete(Share $share): void {
		$this->proxy->sendNotification(
			FederationProvider::NOTIFICATION_DELETE_NODE,
			(string)$share->getNodeId(),
			$share,
			['nodeType' => $share->getNodeType()],
		);
	}

	/**
	 * @throws FederationDisabledError
	 */
	public function isNodeFederated(int $id, string $nodeType): bool {
		if (!$this->configService->isFederationEnabled()) {
			throw new FederationDisabledError('Federation is disabled');
		}

		return match($nodeType) {
			'table' => $this->tableMapper->isFederated($id),
			'view' => $this->viewMapper->isFederated($id),
			default => false,
		};
	}

	/**
	 * @throws FederationDisabledError
	 */
	public function ensureOutgoingFederationEnabled(): void {
		if (!$this->configService->isOutgoingFederationEnabled()) {
			throw new FederationDisabledError('Federation is disabled');
		}
	}

	/**
	 * @throws FederationDisabledError
	 */
	public function ensureIncomingFederationEnabled(): void {
		if (!$this->configService->isIncomingFederationEnabled()) {
			throw new FederationDisabledError('Federation is disabled');
		}
	}
}
