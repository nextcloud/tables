<?php

namespace OCA\Tables\Reference;

use Exception;
use OC\Collaboration\Reference\LinkReferenceProvider;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\ViewService;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\Reference;
use OCP\IConfig;
use OCP\IURLGenerator;
use Throwable;

class ReferenceHelper {
	protected ?string $userId;
	protected IURLGenerator $urlGenerator;
	protected LinkReferenceProvider $linkReferenceProvider;
	protected ViewService $viewService;
	protected ColumnService $columnService;
	protected RowService $rowService;

	protected IConfig $config;

	public function __construct(IURLGenerator $urlGenerator,
		ViewService $viewService,
		ColumnService $columnService,
		RowService $rowService,
		LinkReferenceProvider $linkReferenceProvider,
		?string $userId,
		IConfig $config) {
		$this->userId = $userId;
		$this->urlGenerator = $urlGenerator;
		$this->linkReferenceProvider = $linkReferenceProvider;
		$this->viewService = $viewService;
		$this->rowService = $rowService;
		$this->columnService = $columnService;
		$this->config = $config;
	}

	public function matchReference(string $referenceText): bool {
		return false;
	}

	/** @noinspection PhpUndefinedMethodInspection */
	/** @psalm-suppress InvalidReturnType */
	public function resolveReference(string $referenceText): ?IReference {
		return $this->linkReferenceProvider->resolveReference($referenceText);
	}

	/**
	 * @param string $url
	 * @return int|null
	 */
	public function getTableIdFromLink(string $url): ?int {
		$start = $this->urlGenerator->getAbsoluteURL('/apps/' . Application::APP_ID);
		$startIndex = $this->urlGenerator->getAbsoluteURL('/index.php/apps/' . Application::APP_ID);

		preg_match('/^' . preg_quote($start, '/') . '\/#\/view\/(\d+)(?:\/[^\/]+)*$/i', $url, $matches);
		if (!$matches || count($matches) < 2) {
			preg_match('/^' . preg_quote($startIndex, '/') . '\/#\/view\/(\d+)(?:\/[^\/]+)*$/i', $url, $matches);
		}
		if ($matches && count($matches) > 1) {
			return (int) $matches[1];
		}

		return null;
	}

	public function getCachePrefix(string $referenceId): string {
		return $this->userId ?? '';
	}

	public function getCacheKey(string $referenceId): ?string {
		if ($this->config->getSystemValue('debug')) {
			return $this->randomString(10);
		} else {
			return $referenceId;
		}
	}

	private function randomString(int $length): string {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$randString = '';
		for ($i = 0; $i < $length; $i++) {
			$randString = $characters[rand(0, strlen($characters))];
		}
		return $randString;
	}
}
