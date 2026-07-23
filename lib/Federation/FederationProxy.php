<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Federation;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use OC\Http\Client\Response;
use OCA\Tables\Db\Share;
use OCP\AppFramework\Http;
use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Federation\ICloudIdManager;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Security\Signature\ISignatoryManager;
use OCP\Security\Signature\ISignatureManager;
use Psr\Log\LoggerInterface;
use SensitiveParameter;

class FederationProxy {
	public function __construct(
		private IClientService $clientService,
		private IUserSession $userSession,
		private IConfig $config,
		private IFactory $l10nFactory,
		private LoggerInterface $logger,
		private ICloudFederationProviderManager $federationProviderManager,
		private ICloudFederationFactory $federationFactory,
		private ICloudIdManager $cloudIdManager,
		private ISignatureManager $signatureManager,
		private ISignatoryManager $signatoryManager,
	) {
	}

	protected function generateDefaultRequestOptions(
		#[SensitiveParameter]
		?string $accessToken,
	): array {
		return [
			'verify' => !$this->config->getSystemValueBool('sharing.federation.allowSelfSignedCertificates'),
			'nextcloud' => [
				'allow_local_address' => $this->config->getSystemValueBool('allow_local_remote_servers'),
			],
			'headers' => [
				'Accept' => 'application/json',
				'OCS-APIRequest' => 'true',
				'Accept-Language' => $this->l10nFactory->getUserLanguage($this->userSession->getUser()),
				'tables-federation-accesstoken' => $accessToken,
			],
			'timeout' => 5,
		];
	}

	protected function prependProtocolIfNotAvailable(string $url): string {
		if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
			$url = 'https://' . $url;
		}
		return $url;
	}

	/**
	 * @param 'get'|'post'|'put'|'delete' $verb
	 * @throws \Exception
	 */
	protected function request(
		string $verb,
		#[SensitiveParameter]
		?string $accessToken,
		string $url,
		array $parameters = [],
	): IResponse {
		$requestOptions = $this->prepareSignedRequestOptions($verb, $url, $accessToken, $parameters);

		try {
			return $this->clientService->newClient()->{$verb}(
				$this->prependProtocolIfNotAvailable($url),
				$requestOptions
			);
		} catch (ClientException $e) {
			$status = $e->getResponse()->getStatusCode();
			$body = $e->getResponse()->getBody();
			$content = $body->getContents();
			$body->rewind();

			if (!is_array(json_decode($content, true))) {
				throw new \Exception('Error parsing JSON response', $status);
			}

			$this->logger->debug('Client error from remote', ['exception' => $e]);

			/** @psalm-suppress InvalidReturnStatement */
			return new Response($e->getResponse(), false);
		} catch (ServerException|\Throwable $e) {
			$serverException = new \Exception($e->getMessage(), $e->getCode(), $e);
			$this->logger->error('Could not reach remote', ['exception' => $serverException]);
			throw $serverException;
		}
	}

	public function get(string $shareToken, string $url, array $params = []): IResponse {
		return $this->request('get', $shareToken, $url, $params);
	}

	public function post(string $shareToken, string $url, array $params = []): IResponse {
		return $this->request('post', $shareToken, $url, $params);
	}

	public function put(string $shareToken, string $url, array $params = []): IResponse {
		return $this->request('put', $shareToken, $url, $params);
	}

	public function delete(string $shareToken, string $url): IResponse {
		return $this->request('delete', $shareToken, $url);
	}

	public function getOCSData(IResponse $response, array $allowedStatusCodes = [Http::STATUS_OK]): array {
		if (!in_array($response->getStatusCode(), $allowedStatusCodes, true)) {
			$this->logger->debug('Unexpected status code ' . $response->getStatusCode());
		}

		try {
			$content = $response->getBody();
			$responseData = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
			if (!is_array($responseData)) {
				throw new \RuntimeException('JSON response is not an array');
			}
		} catch (\Throwable $e) {
			$this->logger->error('Error parsing JSON response', ['exception' => $e]);
			throw new \Exception('Error parsing JSON response', $e->getCode(), $e);
		}

		return $responseData['ocs']['data'] ?? [];
	}

	public function sendNotification(string $type, string $providerId, Share $share, array $extra = []): void {
		try {
			$cloudId = $this->cloudIdManager->resolveCloudId($share->getReceiver());
			$notification = $this->federationFactory->getCloudFederationNotification();
			$notification->setMessage($type, FederationProvider::PROVIDER_ID, $providerId,
				array_merge(['sharedSecret' => $share->getToken()], $extra)
			);
			$this->federationProviderManager->sendCloudNotification($cloudId->getRemote(), $notification);
		} catch (\Exception $e) {
			$this->logger->warning('Could not send federated notification', ['exception' => $e]);
		}
	}

	private function prepareSignedRequestOptions(string $verb, string $url, ?string $accessToken, array $parameters = []): array {
		$options = $this->generateDefaultRequestOptions($accessToken);
		$options['body'] = !empty($parameters) ? json_encode($parameters) : '';

		$options = $this->signatureManager->signOutgoingRequestIClientPayload(
			$this->signatoryManager,
			$options,
			$verb,
			$this->prependProtocolIfNotAvailable($url),
		);

		if (!empty($parameters)) {
			$options['json'] = json_decode($options['body'], true);
			unset($options['body']);
		}

		return $options;
	}
}
