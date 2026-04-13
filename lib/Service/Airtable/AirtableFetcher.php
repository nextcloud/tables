<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable;

use OCA\Tables\AppInfo\Application;
use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;

/**
 * Fetches the schema of a publicly shared Airtable base.
 *
 * Two HTTP requests are made against Airtable's undocumented internal endpoints
 * (the same ones the Airtable web UI uses):
 *
 *   Step 1 — GET https://airtable.com/{shareId}
 *             Parse the embedded JavaScript to extract the request headers
 *             (x-airtable-application-id, x-airtable-page-load-id, …) needed
 *             for step 2.
 *
 *   Step 2 — GET https://airtable.com/v0.3/application/{appId}/read
 *             Returns the full schema JSON (tableSchemas, viewDatas, …).
 *
 * ⚠  Both endpoints are unofficial and may change without notice.  The regex
 *    patterns that extract headers from the share page are version-checked at
 *    parse time; when they stop matching, an AirtableFetchException is thrown
 *    with a message that instructs the admin to file a bug report.
 *
 * Implementation is modelled on the equivalent components in Baserow
 * (handler.py / fetch_publicly_shared_base) and NocoDB (helpers/fetchAT.ts).
 */
class AirtableFetcher {

	private const BASE_URL        = 'https://airtable.com';
	private const SCHEMA_PATH     = '/v0.3/application/%s/read';
	private const REQUEST_TIMEOUT = 30;

	/** Share IDs for full base shares always start with this prefix. */
	private const SHARE_ID_PREFIX_BASE = 'shr';
	/** Share IDs for single-view shares start with this prefix. */
	private const SHARE_ID_PREFIX_VIEW = 'shv';

	/** Signals that the page requires authentication. */
	private const LOGIN_INDICATOR = 'id="sign-in"';

	public function __construct(
		private readonly IClientService $clientService,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Fetch the complete schema for a publicly shared Airtable base.
	 *
	 * @param string      $shareUrl      Full Airtable share URL,
	 *                                   e.g. https://airtable.com/shrXXXXXXXX
	 * @param string|null $sessionCookie Value of the __Host-airtable-session
	 *                                   cookie (optional; required for bases
	 *                                   restricted to workspace members).
	 *
	 * @return array<string, mixed> The decoded `data` object from the schema
	 *                              endpoint, containing `tableSchemas`,
	 *                              `viewDatas`, and related keys.
	 *
	 * @throws AirtableBaseNotPublicException    when the share requires login.
	 * @throws AirtableShareIsNotABaseException  when the URL is a view-share.
	 * @throws AirtableFetchException            for all other network / parse errors.
	 */
	public function fetchSchema(string $shareUrl, ?string $sessionCookie = null): array {
		$shareId = $this->extractShareId($shareUrl);

		$this->logger->debug('AirtableFetcher: fetching share page', [
			'app'      => Application::APP_ID,
			'share_id' => $shareId,
		]);

		[$appId, $requestHeaders, $cookies] = $this->fetchSharePageMeta($shareId, $sessionCookie);

		$this->logger->debug('AirtableFetcher: fetching schema', [
			'app'    => Application::APP_ID,
			'app_id' => $appId,
		]);

		return $this->fetchSchemaData($appId, $requestHeaders, $cookies);
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Extract the share-ID path segment from a full Airtable URL.
	 *
	 * @throws AirtableShareIsNotABaseException when the path segment starts
	 *         with the view-share prefix ("shv").
	 * @throws AirtableFetchException           when no recognisable segment
	 *         can be found.
	 */
	private function extractShareId(string $shareUrl): string {
		$parsed = parse_url(trim($shareUrl));
		$path   = ltrim($parsed['path'] ?? '', '/');
		// Take only the first path segment (ignore /tblXXX anchors).
		$shareId = explode('/', $path)[0];

		if ($shareId === '') {
			throw new AirtableFetchException(
				'Could not extract a share ID from the supplied URL: ' . $shareUrl
			);
		}

		if (str_starts_with($shareId, self::SHARE_ID_PREFIX_VIEW)) {
			throw new AirtableShareIsNotABaseException(
				'The supplied URL is a view-share, not a base-share.  ' .
				'Only base-share URLs (https://airtable.com/shr…) can be imported.'
			);
		}

		return $shareId;
	}

	/**
	 * GET the Airtable share page and parse the request headers embedded in
	 * the inline JavaScript.
	 *
	 * Returns a three-element array: [appId, requestHeaders, cookies].
	 *
	 * @return array{0: string, 1: array<string, string>, 2: array<string, string>}
	 *
	 * @throws AirtableBaseNotPublicException
	 * @throws AirtableShareIsNotABaseException
	 * @throws AirtableFetchException
	 */
	private function fetchSharePageMeta(string $shareId, ?string $sessionCookie): array {
		$url     = self::BASE_URL . '/' . $shareId;
		$options = [
			'timeout' => self::REQUEST_TIMEOUT,
			'headers' => [
				'Accept'          => 'text/html,application/xhtml+xml',
				'Accept-Language' => 'en-US,en;q=0.9',
				'User-Agent'      => 'Mozilla/5.0 (compatible; NextcloudTables)',
			],
		];

		if ($sessionCookie !== null) {
			$options['headers']['Cookie'] = '__Host-airtable-session=' . ltrim($sessionCookie, '__Host-airtable-session=');
		}

		try {
			$client   = $this->clientService->newClient();
			$response = $client->get($url, $options);
			$html     = (string) $response->getBody();
		} catch (\Exception $e) {
			throw new AirtableFetchException(
				'Network error while fetching Airtable share page: ' . $e->getMessage(),
				0,
				$e
			);
		}

		if (str_contains($html, self::LOGIN_INDICATOR)) {
			throw new AirtableBaseNotPublicException(
				'The Airtable base requires authentication.  ' .
				'Supply the __Host-airtable-session cookie to import private bases.'
			);
		}

		$headers = $this->parseRequestHeaders($html, $shareId);
		$appId   = $headers['x-airtable-application-id'] ?? '';

		if ($appId === '' || !str_starts_with($appId, 'app')) {
			throw new AirtableShareIsNotABaseException(
				'Could not find a valid application ID on the Airtable share page for "' .
				$shareId . '".  ' .
				'The URL may point to a view or embed share rather than a full base share.  ' .
				'If the URL is correct, Airtable may have changed its page format — ' .
				'please file a bug report.'
			);
		}

		$cookies = [];
		if ($sessionCookie !== null) {
			$cookies['__Host-airtable-session'] = ltrim(
				$sessionCookie,
				'__Host-airtable-session='
			);
		}

		return [$appId, $headers, $cookies];
	}

	/**
	 * Parse the request-headers JSON object embedded in the Airtable share page.
	 *
	 * Airtable embeds something like the following in the page's inline JS:
	 *
	 *   "headers":{"x-airtable-application-id":"appXXXX",
	 *               "x-airtable-page-load-id":"...",
	 *               "x-time-zone":"UTC","x-user-locale":"en"}
	 *
	 * We try two patterns (double-quoted keys, then single-quoted keys) to be
	 * robust across minor page-format variations.
	 *
	 * @return array<string, string>
	 * @throws AirtableFetchException when neither pattern matches.
	 */
	private function parseRequestHeaders(string $html, string $shareId): array {
		// Pattern A: JSON with double-quoted keys (most common)
		if (preg_match('/"headers"\s*:\s*(\{[^}]+\})/s', $html, $m) === 1) {
			$decoded = json_decode($m[1], true);
			if (is_array($decoded)) {
				return $decoded;
			}
		}

		// Pattern B: JS object literal with single-quoted keys (older page format)
		if (preg_match("/headers\s*:\s*(\{[^}]+\})/s", $html, $m) === 1) {
			// Normalise single quotes to double quotes for json_decode
			$normalised = preg_replace("/'/", '"', $m[1]);
			$decoded    = json_decode($normalised ?? '', true);
			if (is_array($decoded)) {
				return $decoded;
			}
		}

		// Pattern C: extract application-id directly as a last resort
		if (preg_match('/"x-airtable-application-id"\s*:\s*"(app[A-Za-z0-9]+)"/', $html, $m) === 1) {
			$this->logger->warning(
				'AirtableFetcher: falling back to direct application-id extraction; ' .
				'page format may have changed',
				['app' => Application::APP_ID, 'share_id' => $shareId]
			);
			return ['x-airtable-application-id' => $m[1]];
		}

		throw new AirtableFetchException(
			'Could not parse request headers from the Airtable share page for "' . $shareId . '".  ' .
			'Airtable may have changed its page format — please file a bug report.'
		);
	}

	/**
	 * GET the Airtable internal schema endpoint and return its `data` payload.
	 *
	 * @param array<string, string> $requestHeaders
	 * @param array<string, string> $cookies
	 * @return array<string, mixed>
	 * @throws AirtableFetchException
	 */
	private function fetchSchemaData(string $appId, array $requestHeaders, array $cookies): array {
		$url = self::BASE_URL . sprintf(self::SCHEMA_PATH, $appId);

		$headers = array_merge($requestHeaders, [
			'Accept'     => 'application/json',
			'User-Agent' => 'Mozilla/5.0 (compatible; NextcloudTables)',
		]);

		if (!empty($cookies)) {
			$cookieString = implode('; ', array_map(
				static fn (string $k, string $v): string => $k . '=' . $v,
				array_keys($cookies),
				array_values($cookies)
			));
			$headers['Cookie'] = $cookieString;
		}

		try {
			$client   = $this->clientService->newClient();
			$response = $client->get($url, [
				'timeout' => self::REQUEST_TIMEOUT,
				'headers' => $headers,
			]);
			$body = (string) $response->getBody();
		} catch (\Exception $e) {
			throw new AirtableFetchException(
				'Network error while fetching Airtable schema for app "' . $appId . '": ' .
				$e->getMessage(),
				0,
				$e
			);
		}

		$decoded = json_decode($body, true);
		if (!is_array($decoded)) {
			throw new AirtableFetchException(
				'Airtable schema endpoint returned non-JSON for app "' . $appId . '".'
			);
		}

		if (!isset($decoded['data']) || !is_array($decoded['data'])) {
			throw new AirtableFetchException(
				'Airtable schema response is missing the expected "data" key for app "' .
				$appId . '".'
			);
		}

		return $decoded['data'];
	}
}
