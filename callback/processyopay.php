<?php

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once dirname(__DIR__) . '/yopay/YoAPI.php';
use WHMCS\Database\Capsule;

header('Content-Type: application/json');

function yopay_json_response($status, $message, array $extra = array())
{
    echo json_encode(array_merge(array(
        'status' => $status,
        'message' => $message,
    ), $extra));
    exit;
}

function yopay_call_api($callback)
{
    set_error_handler(function ($severity, $message, $file, $line) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    try {
        return $callback();
    } finally {
        restore_error_handler();
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    yopay_json_response('error', 'Invalid request method.');
}

$action = isset($_POST['action']) ? trim($_POST['action']) : '';
$gatewayParams = getGatewayVariables('yopay');

if (empty($gatewayParams['type'])) {
    yopay_json_response('error', 'Yo Pay is not active in WHMCS.');
}

try {
    $yoAPI = new YoAPI(
        (string) $gatewayParams['apiUsername'],
        (string) $gatewayParams['apiPassword'],
        $gatewayParams['mode'] === 'sandbox' ? 'sandbox' : 'production'
    );
} catch (Exception $e) {
    yopay_json_response('error', 'Yo Pay could not be initialized. ' . $e->getMessage());
}

if ($action === 'request') {
    $invoiceId = isset($_POST['InvoiceId']) ? (int) $_POST['InvoiceId'] : 0;
    $amount = isset($_POST['Amount']) ? trim($_POST['Amount']) : '';
    $msisdn = isset($_POST['ContactNo']) ? preg_replace('/\D+/', '', $_POST['ContactNo']) : '';
    $description = isset($_POST['Description']) ? trim($_POST['Description']) : 'Invoice Payment';
    $externalReference = isset($_POST['ExternalReference']) ? trim($_POST['ExternalReference']) : '';
    $providerReferenceText = isset($gatewayParams['providerReferenceText']) ? trim($gatewayParams['providerReferenceText']) : '';

    if ($invoiceId <= 0 || $amount === '' || $msisdn === '' || $externalReference === '') {
        yopay_json_response('error', 'Missing invoice, amount, phone number, or external reference.');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    $scriptRoot = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
    $successUrl = $scheme . '://' . $host . $scriptRoot . '/callback/yopay.php';
    $failureUrl = $scheme . '://' . $host . $scriptRoot . '/callback/yopay_failure.php';

    $yoAPI->set_nonblocking('TRUE');
    $yoAPI->set_external_reference($externalReference);
    $yoAPI->set_instant_notification_url($successUrl);
    $yoAPI->set_failure_notification_url($failureUrl);
    if ($providerReferenceText !== '') {
        $yoAPI->set_provider_reference_text($providerReferenceText);
    }

    try {
        $response = yopay_call_api(function () use ($yoAPI, $msisdn, $amount, $description) {
            return $yoAPI->ac_deposit_funds($msisdn, $amount, $description);
        });
    } catch (Throwable $e) {
        error_log('[YoPay][request] ' . $e->getMessage());
        yopay_json_response('error', 'Yo request failed: ' . $e->getMessage());
    }

    if (!is_array($response)) {
        yopay_json_response('error', 'Yo returned an invalid response.');
    }
    $transactionReference = isset($response['TransactionReference']) ? $response['TransactionReference'] : '';
    $status = strtoupper(isset($response['Status']) ? $response['Status'] : '');
    $transactionStatus = strtoupper(isset($response['TransactionStatus']) ? $response['TransactionStatus'] : '');

    if ($transactionReference !== '' || $status === 'OK' || $transactionStatus === 'PENDING') {
        $message = isset($response['StatusMessage']) && $response['StatusMessage'] !== ''
            ? $response['StatusMessage']
            : 'Yo has accepted the request. Approve it on the customer phone and check status if needed.';

        yopay_json_response('success', $message, array(
            'transaction_reference' => $transactionReference,
            'transaction_status' => $transactionStatus,
        ));
    }

    $errorMessage = isset($response['ErrorMessage']) && $response['ErrorMessage'] !== ''
        ? $response['ErrorMessage']
        : (isset($response['StatusMessage']) ? $response['StatusMessage'] : 'Yo rejected the payment request.');

    yopay_json_response('error', $errorMessage);
}

if ($action === 'status') {
    $transactionReference = isset($_POST['TransactionReference']) ? trim($_POST['TransactionReference']) : '';
    $externalReference = isset($_POST['ExternalReference']) ? trim($_POST['ExternalReference']) : '';

    if ($transactionReference === '' && $externalReference === '') {
        yopay_json_response('error', 'A transaction reference is required to check status.');
    }

    try {
        $response = yopay_call_api(function () use ($yoAPI, $transactionReference, $externalReference) {
            return $yoAPI->ac_transaction_check_status(
                $transactionReference !== '' ? $transactionReference : null,
                $externalReference !== '' ? $externalReference : null
            );
        });
    } catch (Throwable $e) {
        error_log('[YoPay][status] ' . $e->getMessage());
        yopay_json_response('error', 'Yo status check failed: ' . $e->getMessage());
    }

    if (!is_array($response)) {
        yopay_json_response('error', 'Yo returned an invalid status response.');
    }

    $transactionStatus = strtoupper(isset($response['TransactionStatus']) ? $response['TransactionStatus'] : '');
    $statusMessage = isset($response['StatusMessage']) ? $response['StatusMessage'] : '';

    if ($transactionStatus === 'SUCCEEDED') {
        yopay_json_response('success', 'Yo reports this payment as successful. The invoice callback should mark it paid shortly. Refresh the page if needed.');
    }

    if ($transactionStatus === 'PENDING' || $transactionStatus === 'INDETERMINATE') {
        yopay_json_response('info', $statusMessage !== '' ? $statusMessage : 'Yo still shows this transaction as pending.');
    }

    $errorMessage = isset($response['ErrorMessage']) && $response['ErrorMessage'] !== ''
        ? $response['ErrorMessage']
        : ($statusMessage !== '' ? $statusMessage : 'Yo reports that the transaction was not successful.');

    yopay_json_response('error', $errorMessage);
}

if ($action === 'invoice_status') {
    $invoiceId = isset($_POST['InvoiceId']) ? (int) $_POST['InvoiceId'] : 0;

    if ($invoiceId <= 0) {
        yopay_json_response('error', 'Invalid invoice reference.');
    }

    $invoiceStatus = Capsule::table('tblinvoices')
        ->where('id', $invoiceId)
        ->value('status');

    if (is_string($invoiceStatus) && strtolower($invoiceStatus) === 'paid') {
        yopay_json_response('success', 'Invoice marked paid.');
    }

    yopay_json_response('info', 'Invoice is still awaiting payment confirmation.');
}

yopay_json_response('error', 'Unsupported action.');
