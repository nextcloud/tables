<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023, Julien Veyssier
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\Tables\Search;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\View;
use OCA\Tables\Service\ViewService;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;

class SearchTablesProvider implements IProvider {
	private IAppManager $appManager;
	private IL10N $l10n;
	private ViewService $viewService;
	private IURLGenerator $urlGenerator;

	public function __construct(IAppManager   $appManager,
		IL10N         $l10n,
								ViewService  $viewService,
		IURLGenerator $urlGenerator) {
		$this->appManager = $appManager;
		$this->l10n = $l10n;
		$this->viewService = $viewService;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'tables-search-tables';
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->l10n->t('Nextcloud tables');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(string $route, array $routeParameters): int {
		if (strpos($route, Application::APP_ID . '.') === 0) {
			// Active app, prefer Tables results
			return -1;
		}

		return 20;
	}

	/**
	 * @inheritDoc
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		if (!$this->appManager->isEnabledForUser(Application::APP_ID, $user)) {
			return SearchResult::complete($this->getName(), []);
		}

		$limit = $query->getLimit();
		$term = $query->getTerm();
		$offset = $query->getCursor();
		$offset = $offset ? (int) $offset : 0;

		$views = $this->viewService->search($term, $limit, $offset);

		$appIconUrl = $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
		);

		$formattedResults = array_map(function (View $view) use ($appIconUrl): SearchResultEntry {
			return new SearchResultEntry(
				$appIconUrl,
				$view->getEmoji() .' '. $view->getTitle(),
				($view->getOwnerDisplayName() ?? $view->getOwnership()) . ', ' . $this->l10n->n('%n row', '%n rows', $view->getRowsCount()),
				$this->getInternalLink($view),
				'',
				false
			);
		}, $views);

		return SearchResult::paginated(
			$this->getName(),
			$formattedResults,
			$offset + $limit
		);
	}

	/**
	 * @param View $view
	 * @return string
	 */
	protected function getInternalLink(View $view): string {
		return $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.page.index')
			. '#/view/' . $view->getId();
	}
}
