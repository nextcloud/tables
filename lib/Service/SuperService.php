<?php

namespace OCA\Tables\Service;

use Psr\Log\LoggerInterface;

class SuperService {
	protected PermissionsService $permissionsService;

	protected LoggerInterface $logger;

	protected ?string $userId;

	public function __construct(LoggerInterface $logger, ?string $userId, PermissionsService $permissionsService) {
		$this->permissionsService = $permissionsService;
		$this->logger = $logger;
		$this->userId = $userId;
	}

	public function AdminNotificationCall($tableId,$viewId) {
	 
        $url = 'http://admin:admin@localhost/ocs/v2.php/apps/notifications/api/v2/admin_notifications/admin';

		$conditionalTerm = (!is_null($tableId)) ? 'Table Id '.$tableId: 'View Id '.$viewId;
		
		$data = array(
			'shortMessage' => 'Alert !',
			'longMessage'  => 'A new entry created in '.$conditionalTerm
		);

		// Initialize cURL session
		$ch = curl_init($url);

		// Set cURL options
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('OCS-APIREQUEST: true'));
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		// Execute cURL session and store the response
		$response = curl_exec($ch);

		// Check for cURL errors
		if (curl_errno($ch)) { 
			echo 'cURL error: ' . curl_error($ch);
		}

		// Close cURL session
		curl_close($ch);

		// Output the response
		return $response;
	}
}
