<?php

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
require_once dirname(__DIR__) . '/yopay/YoAPI.php';

$gatewayModuleName = basename(__FILE__, '.php');
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

$notification = $yoAPI->receive_payment_notification();
logTransaction($gatewayParams['name'], $notification, $notification['is_verified'] ? 'Notification Received' : 'Verification Failed');

if (!$notification['is_verified']) {
    http_response_code(400);
    exit('Verification failed');
}

$externalReference = isset($notification['external_ref']) ? (string) $notification['external_ref'] : '';
if (!preg_match('/INV(\d+)-/i', $externalReference, $matches)) {
    http_response_code(400);
    exit('Invalid external reference');
}

$invoiceId = checkCbInvoiceID((int) $matches[1], $gatewayParams['name']);
$transactionId = !empty($notification['network_ref']) ? $notification['network_ref'] : $externalReference;
checkCbTransID($transactionId);

$paymentAmount = isset($notification['amount']) ? (float) $notification['amount'] : 0;
addInvoicePayment(
    $invoiceId,
    $transactionId,
    $paymentAmount,
    0,
    $gatewayModuleName
);

echo 'OK';
