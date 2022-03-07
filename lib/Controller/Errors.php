<?php

namespace OCA\Tables\Controller;

use Closure;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

trait Errors {
	protected function handleError(Closure $callback): DataResponse {
		try {
			return new DataResponse($callback());
        } catch (PermissionError $e) {
            $message = ['message' => $e->getMessage()];
            return new DataResponse($message, Http::STATUS_FORBIDDEN);
        } catch (NotFoundError $e) {
            $message = ['message' => $e->getMessage()];
            return new DataResponse($message, Http::STATUS_NOT_FOUND);
        } catch (InternalError|\Exception $e) {
            $message = ['message' => $e->getMessage()];
            return new DataResponse($message, Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
