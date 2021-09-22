<?php

namespace OCA\Tables\Controller;

use Closure;

use OCA\Tables\Service\TableNotFound;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

trait Errors {
	protected function handleNotFound(Closure $callback): DataResponse {
		try {
			return new DataResponse($callback());
		} catch (TableNotFound $e) {
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}
}
