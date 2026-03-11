# Yo Pay WHMCS Gateway

WHMCS payment gateway module for collecting mobile money payments through Yo Payments.

## Included Files

This folder contains:

- `yopay.php`
- `callback/processyopay.php`
- `callback/yopay.php`
- `callback/yopay_failure.php`
- `yopay/YoAPI.php`
- `yopay/Yo_Uganda_Public_Certificate.crt`
- `yopay/Yo_Uganda_Public_Sandbox_Certificate.crt`
- `yopay/whmcs.json`

The PDFs in this folder are the local Yo API reference documents used while building the module.

## Install Into WHMCS

Copy the files into your WHMCS installation like this:

```text
modules/gateways/yopay.php
modules/gateways/callback/processyopay.php
modules/gateways/callback/yopay.php
modules/gateways/callback/yopay_failure.php
modules/gateways/yopay/YoAPI.php
modules/gateways/yopay/Yo_Uganda_Public_Certificate.crt
modules/gateways/yopay/Yo_Uganda_Public_Sandbox_Certificate.crt
modules/gateways/yopay/whmcs.json
```

## Activate the Gateway

1. Log in to WHMCS admin.
2. Open `Setup > Payments > Payment Gateways`.
3. Activate `Yo Pay Mobile Money`.
4. Enter your Yo API username and password.
5. Select `sandbox` or `production`.
6. Save the gateway settings.

## Required WHMCS Client Data

The module sends the payment request to the client phone number saved in WHMCS.

It builds the mobile number from:

- client phone country code
- client phone number

The resulting number is sent to Yo in the format `2567XXXXXXXX`.

## Payment Flow

1. The client opens an unpaid invoice.
2. They choose `Yo Pay Mobile Money`.
3. The module shows a checkout card on the invoice page.
4. The client clicks `Request Payment`.
5. Yo sends a mobile money push request to the customer phone.
6. The customer approves the request on the phone.
7. Yo sends a signed success callback to WHMCS.
8. WHMCS marks the invoice as paid.

## Callbacks

The module uses these callback endpoints:

- success: `modules/gateways/callback/yopay.php`
- failure: `modules/gateways/callback/yopay_failure.php`
- ajax request/status: `modules/gateways/callback/processyopay.php`

The success and failure notifications are verified using Yo public certificates included in the `yopay/` folder.

## Notes

- Sandbox and production modes are both supported.
- The module uses Yo's official PHP library flow and non-blocking deposit requests.
- If the invoice does not refresh immediately after approval, the client can use `Check Payment Status`.
- Your WHMCS installation must be reachable publicly for Yo callbacks to work in production.

## Local Test Install

This workspace also has the module copied into:

```text
whmcs-caystard/modules/gateways/
```

Files installed there:

- `whmcs-caystard/modules/gateways/yopay.php`
- `whmcs-caystard/modules/gateways/callback/processyopay.php`
- `whmcs-caystard/modules/gateways/callback/yopay.php`
- `whmcs-caystard/modules/gateways/callback/yopay_failure.php`
- `whmcs-caystard/modules/gateways/yopay/`
