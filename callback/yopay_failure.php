<?php

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once dirname(__DIR__) . '/yopay/YoAPI.php';

$gatewayModuleName = 'yopay';
$gatewayParams = getGatewayVariables($gatewayModuleName);

if (!$gatewayParams['type']) {
    http_response_code(503);
    exit('Module Not Activated');
}

$yoAPI = new YoAPI(
    $gatewayParams['apiUsername'],
    $gatewayParams['apiPassword'],
    $gatewayParams['mode'] === 'sandbox' ? 'sandbox' : 'production'
);

$notification = $yoAPI->receive_payment_failure_notification();
logTransaction($gatewayParams['name'], $notification, $notification['is_verified'] ? 'Failure Notification Received' : 'Failure Verification Failed');

if (!$notification['is_verified']) {
    http_response_code(400);
    exit('Verification failed');
}

echo 'OK';
