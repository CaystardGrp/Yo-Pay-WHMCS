<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function yopay_MetaData()
{
    return array(
        'DisplayName' => 'Yo Pay Mobile Money',
        'APIVersion' => '1.1',
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}

function yopay_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Yo Pay Mobile Money',
        ),
        'apiUsername' => array(
            'FriendlyName' => 'API Username',
            'Type' => 'text',
            'Size' => '40',
            'Description' => 'Your Yo Payments API username.',
        ),
        'apiPassword' => array(
            'FriendlyName' => 'API Password',
            'Type' => 'password',
            'Size' => '40',
            'Description' => 'Your Yo Payments API password.',
        ),
        'mode' => array(
            'FriendlyName' => 'Mode',
            'Type' => 'dropdown',
            'Options' => array(
                'production' => 'Production',
                'sandbox' => 'Sandbox',
            ),
            'Default' => 'production',
            'Description' => 'Use sandbox while testing.',
        ),
        'providerReferenceText' => array(
            'FriendlyName' => 'Provider SMS Text',
            'Type' => 'text',
            'Size' => '60',
            'Description' => 'Optional text appended to provider confirmation SMS.',
        ),
        'narrativePrefix' => array(
            'FriendlyName' => 'Narrative Prefix',
            'Type' => 'text',
            'Size' => '60',
            'Default' => 'Invoice Payment',
            'Description' => 'Short label used in the Yo payment request.',
        ),
    );
}

function yopay_build_msisdn(array $clientDetails)
{
    $countryCode = preg_replace('/\D+/', '', (string) ($clientDetails['phonecc'] ?? ''));
    $phone = preg_replace('/\D+/', '', (string) ($clientDetails['phonenumber'] ?? ''));

    if ($phone === '') {
        return '';
    }

    if ($countryCode !== '' && strpos($phone, $countryCode) === 0) {
        return $phone;
    }

    if (strpos($phone, '0') === 0 && $countryCode !== '') {
        $phone = ltrim($phone, '0');
    }

    return $countryCode . $phone;
}

function yopay_build_external_reference($invoiceId)
{
    $seed = bin2hex(random_bytes(4));
    return 'INV' . (int) $invoiceId . '-' . strtoupper($seed);
}

function yopay_link($params)
{
    $invoiceId = (int) $params['invoiceid'];
    $description = trim((string) $params['description']);
    $amount = (string) $params['amount'];
    $currencyCode = (string) $params['currency'];
    $companyName = (string) $params['companyname'];
    $systemUrl = rtrim((string) $params['systemurl'], '/');
    $narrativePrefix = trim((string) $params['narrativePrefix']);
    $customerNo = yopay_build_msisdn($params['clientdetails']);
    $customerName = trim($params['clientdetails']['firstname'] . ' ' . $params['clientdetails']['lastname']);

    if ($customerNo === '') {
        return '<div class="alert alert-danger">A valid phone number is required on the client profile before Yo Pay can be used.</div>';
    }

    $externalReference = yopay_build_external_reference($invoiceId);
    $ajaxUrl = $systemUrl . '/modules/gateways/callback/processyopay.php';
    $narrative = trim(($narrativePrefix !== '' ? $narrativePrefix : 'Invoice Payment') . ' #' . $invoiceId);
    $returnUrl = (string) $params['returnurl'];

    $postfields = array(
        'description' => $narrative,
        'invoice_id' => $invoiceId,
        'amount' => $amount,
        'currency' => $currencyCode,
        'msisdn' => $customerNo,
        'external_reference' => $externalReference,
        'ajax_url' => $ajaxUrl,
        'return_url' => $returnUrl,
    );

    $safeCompanyName = htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8');
    $safeDescription = htmlspecialchars($description !== '' ? $description : $narrative, ENT_QUOTES, 'UTF-8');
    $safeAmount = htmlspecialchars($amount, ENT_QUOTES, 'UTF-8');
    $safeCurrencyCode = htmlspecialchars($currencyCode, ENT_QUOTES, 'UTF-8');
    $safeCustomerNo = htmlspecialchars('+' . $customerNo, ENT_QUOTES, 'UTF-8');
    $safeCustomerName = htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8');
    $safeLogoUrl = htmlspecialchars($systemUrl . '/modules/gateways/yopay/logo.png', ENT_QUOTES, 'UTF-8');

    $htmlOutput = '
    <style>
        .yopay-card {
            position: relative;
            overflow: hidden;
            max-width: 680px;
            padding: 28px;
            border: 1px solid rgba(17, 24, 39, 0.08);
            border-radius: 28px;
            background:
                radial-gradient(circle at top left, rgba(99, 102, 241, 0.24), transparent 28%),
                radial-gradient(circle at bottom right, rgba(59, 130, 246, 0.14), transparent 32%),
                linear-gradient(140deg, #060918 0%, #10163b 46%, #f5f7ff 46%, #f5f7ff 100%);
            box-shadow: 0 28px 72px rgba(15, 23, 42, 0.16);
            color: #111827;
            font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
        }

        .yopay-card::after {
            content: "";
            position: absolute;
            right: -110px;
            bottom: -120px;
            width: 280px;
            height: 280px;
            border-radius: 50%;
            background: rgba(99, 102, 241, 0.12);
        }

        .yopay-card__inner {
            position: relative;
            z-index: 1;
        }

        .yopay-card__top {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 22px;
        }

        .yopay-card__brand {
            display: flex;
            align-items: center;
            gap: 14px;
            color: #eef2ff;
        }

        .yopay-card__logo-shell {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 16px;
            border-radius: 20px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(238, 242, 255, 0.92));
            border: 1px solid rgba(165, 180, 252, 0.32);
            box-shadow: 0 16px 30px rgba(15, 23, 42, 0.22);
        }

        .yopay-card__logo {
            display: block;
            width: auto;
            max-width: 190px;
            max-height: 42px;
        }

        .yopay-card__eyebrow {
            margin: 0 0 4px;
            font-size: 11px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            font-weight: 700;
            opacity: 0.72;
        }

        .yopay-card__title {
            margin: 0;
            color: #ffffff;
            font-size: 28px;
            line-height: 1.06;
            font-weight: 900;
        }

        .yopay-card__pill {
            padding: 9px 14px;
            border-radius: 999px;
            background: rgba(99, 102, 241, 0.14);
            border: 1px solid rgba(165, 180, 252, 0.2);
            color: #eef2ff;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .yopay-card__body {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(280px, 0.92fr);
            gap: 20px;
        }

        .yopay-card__panel,
        .yopay-card__summary {
            padding: 22px;
            border-radius: 24px;
        }

        .yopay-card__panel {
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid rgba(15, 23, 42, 0.08);
        }

        .yopay-card__summary {
            background: rgba(10, 14, 36, 0.94);
            color: #dbeafe;
            border: 1px solid rgba(99, 102, 241, 0.16);
        }

        .yopay-card__label {
            margin: 0 0 8px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #4f46e5;
        }

        .yopay-card__amount {
            margin: 0;
            color: #111111;
            font-size: 38px;
            line-height: 1;
            font-weight: 900;
        }

        .yopay-card__description {
            margin: 14px 0 0;
            color: #4b5563;
            font-size: 14px;
            line-height: 1.65;
        }

        .yopay-card__meta {
            display: grid;
            gap: 12px;
            margin-top: 20px;
        }

        .yopay-card__meta-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(17, 24, 39, 0.08);
            font-size: 14px;
        }

        .yopay-card__meta-row:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .yopay-card__meta-key {
            color: #6b7280;
        }

        .yopay-card__meta-value {
            font-weight: 700;
            color: #111827;
            text-align: right;
        }

        .yopay-card__summary-title {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            color: #eef2ff;
        }

        .yopay-card__summary-copy {
            margin: 12px 0 0;
            font-size: 14px;
            line-height: 1.7;
            color: rgba(219, 234, 254, 0.84);
        }

        .yopay-card__notice,
        .yopay-card__status {
            margin-top: 18px;
            padding: 14px 16px;
            border-radius: 18px;
            font-size: 14px;
            line-height: 1.6;
        }

        .yopay-card__notice {
            background: rgba(79, 70, 229, 0.16);
            color: #eef2ff;
        }

        .yopay-card__status {
            display: none;
            font-weight: 600;
        }

        .yopay-card__status.is-visible {
            display: block;
        }

        .yopay-card__status.is-info {
            background: #eef2ff;
            color: #3730a3;
            border: 1px solid #c7d2fe;
        }

        .yopay-card__status.is-success {
            background: #ecfdf3;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .yopay-card__status.is-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .yopay-card__actions {
            display: grid;
            gap: 12px;
            margin-top: 22px;
        }

        .yopay-card__button {
            width: 100%;
            padding: 16px 20px;
            border: 0;
            border-radius: 18px;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            transition: transform 0.2s ease, opacity 0.2s ease, box-shadow 0.2s ease;
        }

        .yopay-card__button:hover,
        .yopay-card__button:focus {
            transform: translateY(-1px);
        }

        .yopay-card__button[disabled] {
            cursor: wait;
            opacity: 0.82;
            transform: none;
        }

        .yopay-card__button--primary {
            background: linear-gradient(135deg, #4f46e5 0%, #2563eb 100%);
            color: #ffffff;
            box-shadow: 0 16px 30px rgba(37, 99, 235, 0.28);
        }

        .yopay-card__button--secondary {
            background: rgba(99, 102, 241, 0.12);
            color: #eef2ff;
            border: 1px solid rgba(165, 180, 252, 0.16);
        }

        .yopay-card__button-spinner {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .yopay-card__button.is-loading .yopay-card__button-label {
            display: none;
        }

        .yopay-card__button.is-loading .yopay-card__button-spinner {
            display: inline-flex;
        }

        .yopay-card__spinner-ring {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.28);
            border-top-color: #ffffff;
            animation: yopaySpin 0.85s linear infinite;
        }

        @keyframes yopaySpin {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 640px) {
            .yopay-card {
                padding: 18px;
                border-radius: 24px;
            }

            .yopay-card__body {
                grid-template-columns: 1fr;
            }

            .yopay-card__title {
                font-size: 24px;
            }

            .yopay-card__amount {
                font-size: 32px;
            }
        }
    </style>
    <div class="yopay-card">
        <div class="yopay-card__inner">';

    foreach ($postfields as $key => $value) {
        $htmlOutput .= '<input type="hidden" id="' . $key . '" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '" />';
    }

    $htmlOutput .= '
            <input type="hidden" id="transaction_reference" value="" />
            <div class="yopay-card__top">
                <div class="yopay-card__brand">
                    <div class="yopay-card__logo-shell">
                        <img class="yopay-card__logo" src="' . $safeLogoUrl . '" alt="Yo Payments logo" />
                    </div>
                    <div>
                        <p class="yopay-card__eyebrow">' . $safeCompanyName . '</p>
                        <h3 class="yopay-card__title">Yo Pay Checkout</h3>
                    </div>
                </div>
                <div class="yopay-card__pill">Mobile Money Push</div>
            </div>
            <div class="yopay-card__body">
                <div class="yopay-card__panel">
                    <p class="yopay-card__label">Amount Due</p>
                    <p class="yopay-card__amount">' . $safeCurrencyCode . ' ' . $safeAmount . '</p>
                    <p class="yopay-card__description">' . $safeDescription . '</p>
                    <div class="yopay-card__meta">
                        <div class="yopay-card__meta-row">
                            <span class="yopay-card__meta-key">Invoice</span>
                            <span class="yopay-card__meta-value">#' . htmlspecialchars((string) $invoiceId, ENT_QUOTES, 'UTF-8') . '</span>
                        </div>
                        <div class="yopay-card__meta-row">
                            <span class="yopay-card__meta-key">Customer</span>
                            <span class="yopay-card__meta-value">' . $safeCustomerName . '</span>
                        </div>
                        <div class="yopay-card__meta-row">
                            <span class="yopay-card__meta-key">Charge phone</span>
                            <span class="yopay-card__meta-value">' . $safeCustomerNo . '</span>
                        </div>
                    </div>
                </div>
                <div class="yopay-card__summary">
                    <h4 class="yopay-card__summary-title">How it works</h4>
                    <p class="yopay-card__summary-copy">Request the payment prompt, approve it on the mobile money phone, then check the transaction status here if the invoice page does not refresh immediately.</p>
                    <div class="yopay-card__notice">Yo Pay will send the collection request to <strong>' . $safeCustomerNo . '</strong>. Update the client phone number in WHMCS first if this is not correct.</div>
                    <div id="yopay-status" class="yopay-card__status" aria-live="polite"></div>
                    <div class="yopay-card__actions">
                        <button id="yopay-request-button" type="button" class="yopay-card__button yopay-card__button--primary" onclick="yopayRequestPayment();">
                            <span class="yopay-card__button-label">Request Payment</span>
                            <span class="yopay-card__button-spinner">
                                <span class="yopay-card__spinner-ring" aria-hidden="true"></span>
                                Sending request...
                            </span>
                        </button>
                        <button id="yopay-check-button" type="button" class="yopay-card__button yopay-card__button--secondary" onclick="yopayCheckStatus();" disabled>
                            Check Payment Status
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script type="text/javascript">
        function yopaySetStatus(type, message) {
            var box = $("#yopay-status");
            box.removeClass("is-info is-success is-error").addClass("is-visible");
            if (type === "success") {
                box.addClass("is-success");
            } else if (type === "error") {
                box.addClass("is-error");
            } else {
                box.addClass("is-info");
            }
            box.text(message);
        }

        function yopayRequestPayment() {
            var requestButton = $("#yopay-request-button");
            var checkButton = $("#yopay-check-button");

            requestButton.prop("disabled", true).addClass("is-loading");
            yopaySetStatus("info", "Sending Yo payment prompt. Approve it on your phone when it appears.");

            $.ajax({
                url: $("#ajax_url").val(),
                type: "POST",
                dataType: "json",
                data: {
                    action: "request",
                    Description: $("#description").val(),
                    InvoiceId: $("#invoice_id").val(),
                    Amount: $("#amount").val(),
                    Currency: $("#currency").val(),
                    ContactNo: $("#msisdn").val(),
                    ExternalReference: $("#external_reference").val()
                },
                success: function(response) {
                    if (response.status === "success") {
                        if (response.transaction_reference) {
                            $("#transaction_reference").val(response.transaction_reference);
                            checkButton.prop("disabled", false);
                        }
                        yopaySetStatus("success", response.message);
                        yopayWatchPayment();
                    } else {
                        yopaySetStatus("error", response.message);
                    }
                },
                error: function(xhr) {
                    var message = "The Yo payment request could not be started right now. Please try again.";
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    } else if (xhr.responseText) {
                        try {
                            var parsed = JSON.parse(xhr.responseText);
                            if (parsed.message) {
                                message = parsed.message;
                            }
                        } catch (e) {}
                    }
                    yopaySetStatus("error", message);
                },
                complete: function() {
                    requestButton.prop("disabled", false).removeClass("is-loading");
                }
            });
        }

        function yopayCheckStatus() {
            var transactionReference = $("#transaction_reference").val();
            if (!transactionReference) {
                yopaySetStatus("error", "Request a payment first so Yo returns a transaction reference.");
                return;
            }

            yopaySetStatus("info", "Checking the latest Yo transaction status...");

            $.ajax({
                url: $("#ajax_url").val(),
                type: "POST",
                dataType: "json",
                data: {
                    action: "status",
                    TransactionReference: transactionReference,
                    ExternalReference: $("#external_reference").val()
                },
                success: function(response) {
                    if (response.status === "success") {
                        yopaySetStatus("success", response.message);
                        yopayWatchPayment();
                    } else if (response.status === "info") {
                        yopaySetStatus("info", response.message);
                    } else {
                        yopaySetStatus("error", response.message);
                    }
                },
                error: function(xhr) {
                    var message = "Yo status check failed. Refresh the invoice page and try again.";
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    } else if (xhr.responseText) {
                        try {
                            var parsed = JSON.parse(xhr.responseText);
                            if (parsed.message) {
                                message = parsed.message;
                            }
                        } catch (e) {}
                    }
                    yopaySetStatus("error", message);
                }
            });
        }

        function yopayWatchPayment() {
            var ajaxUrl = $("#ajax_url").val();
            var invoiceId = $("#invoice_id").val();
            var returnUrl = $("#return_url").val();
            var attempts = 0;
            var maxAttempts = 24;
            var fallbackRedirectMs = 30000;

            window.setTimeout(function() {
                window.location.href = returnUrl;
            }, fallbackRedirectMs);

            var pollTimer = window.setInterval(function() {
                attempts++;

                $.ajax({
                    url: ajaxUrl,
                    type: "POST",
                    dataType: "json",
                    data: {
                        action: "invoice_status",
                        InvoiceId: invoiceId
                    },
                    success: function(response) {
                        if (response.status === "success") {
                            window.clearInterval(pollTimer);
                            window.location.href = returnUrl;
                        } else if (attempts >= maxAttempts) {
                            window.clearInterval(pollTimer);
                            window.location.href = returnUrl;
                        }
                    },
                    error: function() {
                        if (attempts >= maxAttempts) {
                            window.clearInterval(pollTimer);
                            window.location.href = returnUrl;
                        }
                    }
                });
            }, 5000);
        }
    </script>';

    return $htmlOutput;
}
