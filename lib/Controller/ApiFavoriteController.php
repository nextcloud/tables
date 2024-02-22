<?php

namespace OCA\Tables\Controller;

use Exception;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Service\FavoritesService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TablesTable from ResponseDefinitions
 */
class ApiFavoriteController extends AOCSController {
	private FavoritesService $service;

	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		FavoritesService $service,
		IL10N $n,
		string $userId) {
		parent::__construct($request, $logger, $n, $userId);
		$this->service = $service;
	}

	/**
	 * [api v2] Create a new table and return it
	 *
	 * @NoAdminRequired
	 *
	 * @param string $title Title of the table
	 * @param string|null $emoji Emoji for the table
	 * @param string $template Template to use if wanted
	 *
	 * @return DataResponse<Http::STATUS_OK, TablesTable, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: Tables returned
	 */
	public function create(int $nodeType, int $nodeId): DataResponse {
		try {
			$this->service->addFavorite($nodeType, $nodeId);
			return new DataResponse(['ok']);
		} catch (InternalError|Exception $e) {
			return $this->handleError($e);
		}
	}


	/**
	 * [api v2] Delete a table
	 *
	 * @NoAdminRequired
	 *
	 * @param int $id Table ID
	 * @return DataResponse<Http::STATUS_OK, TablesTable, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_INTERNAL_SERVER_ERROR|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Deleted table returned
	 * 403: No permissions
	 * 404: Not found
	 */
	public function destroy(int $nodeType, int $nodeId): DataResponse {
		try {
			$this->service->removeFavorite($nodeType, $nodeId);
			return new DataResponse(['ok']);
		} catch (PermissionError $e) {
			return $this->handlePermissionError($e);
		} catch (InternalError $e) {
			return $this->handleError($e);
		} catch (NotFoundError $e) {
			return $this->handleNotFoundError($e);
		}
	}
}
