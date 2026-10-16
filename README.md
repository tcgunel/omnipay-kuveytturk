# Omnipay: KuveytTurk

[![Tests](https://github.com/tcgunel/omnipay-kuveytturk/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/tcgunel/omnipay-kuveytturk/actions/workflows/tests.yml)
[![Latest Stable Version](https://img.shields.io/packagist/v/tcgunel/omnipay-kuveytturk.svg?label=stable)](https://packagist.org/packages/tcgunel/omnipay-kuveytturk)
[![Total Downloads](https://img.shields.io/packagist/dt/tcgunel/omnipay-kuveytturk.svg)](https://packagist.org/packages/tcgunel/omnipay-kuveytturk)
[![PHP Version](https://img.shields.io/packagist/php-v/tcgunel/omnipay-kuveytturk.svg)](https://packagist.org/packages/tcgunel/omnipay-kuveytturk)
[![License](https://img.shields.io/packagist/l/tcgunel/omnipay-kuveytturk.svg)](LICENSE)

**KuveytTurk sanal pos gateway for the Omnipay PHP payment processing library.**

[Omnipay](https://github.com/thephpleague/omnipay) is a framework agnostic, multi-gateway payment
processing library for PHP. This package implements KuveytTurk (Kuveyt Türk Bankası) support for Omnipay.

> **v3.0 — Breaking change.** v3 migrates to KuveytTurk's new `KTPay/*` JSON endpoints with the HMAC-SHA512 hash. The 3D callback now reads `Result_MD` / `Result_OrderId` / `Result_MerchantOrderId` instead of `AuthenticationResponse`. See [UPGRADING.md](#upgrading-from-v2x).

## Installation

```bash
composer require tcgunel/omnipay-kuveytturk:^3.0
```

## Usage

### Gateway Parameters

| Parameter    | Description                                       |
|--------------|---------------------------------------------------|
| `merchantId` | Merchant ID                                       |
| `customerId` | Customer ID                                       |
| `userName`   | API Username                                      |
| `password`   | API Password (plain text — hashed internally)     |
| `installment`| Installment count (0 or 1 = single payment)       |
| `secure`     | Use 3D Secure (true / false)                      |

### Non-3D Purchase

```php
use Omnipay\Omnipay;

$gateway = Omnipay::create('KuveytTurk');

$gateway->setMerchantId('YOUR_MERCHANT_ID');
$gateway->setCustomerId('YOUR_CUSTOMER_ID');
$gateway->setUserName('YOUR_USERNAME');
$gateway->setPassword('YOUR_PASSWORD');
$gateway->setTestMode(true);

$response = $gateway->purchase([
    'secure'        => false,
    'amount'        => '12.34',
    'currency'      => 'TRY',
    'transactionId' => 'ORDER-001',
    'installment'   => 0,
    'card'          => [
        'firstName'   => 'John',
        'lastName'    => 'Doe',
        'number'      => '4111111111111111',
        'expiryMonth' => '12',
        'expiryYear'  => '2030',
        'cvv'         => '123',
    ],
])->send();

if ($response->isSuccessful()) {
    echo "Payment successful! Code: " . $response->getCode();
} else {
    echo "Payment failed: " . $response->getMessage();
}
```

### 3D Secure Purchase

**Step 1 — server-to-server call to the bank, then stream the bank's HTML auto-submit form to the browser.**

```php
$response = $gateway->purchase([
    'secure'        => true,
    'amount'        => '12.34',
    'currency'      => 'TRY',
    'transactionId' => 'ORDER-001',
    'installment'   => 0,
    'returnUrl'     => 'https://yoursite.com/payment/success',
    'cancelUrl'     => 'https://yoursite.com/payment/failure',
    'card'          => [
        'firstName'   => 'John',
        'lastName'    => 'Doe',
        'number'      => '4111111111111111',
        'expiryMonth' => '12',
        'expiryYear'  => '2030',
        'cvv'         => '123',
        'email'       => 'customer@example.com',
        'phone'       => '+905551112233',
    ],
])->send();

if ($response->isRedirect()) {
    // Bank returned an HTML auto-submit form. Echo it to the user's browser.
    echo $response->getRedirectResponse()->getContent();
    exit;
}

// Otherwise the bank returned a JSON error
echo "3D init failed: " . $response->getMessage();
```

**Step 2 — bank POSTs back to your `returnUrl` with `Result_MD`, `Result_OrderId`, `Result_MerchantOrderId`. Capture the funds via Provision.**

```php
$response = $gateway->completePurchase([
    'merchantId'    => 'YOUR_MERCHANT_ID',
    'customerId'    => 'YOUR_CUSTOMER_ID',
    'userName'      => 'YOUR_USERNAME',
    'password'      => 'YOUR_PASSWORD',
    'transactionId' => $_POST['Result_MerchantOrderId'],
    'orderId'       => $_POST['Result_OrderId'],
    'md'            => $_POST['Result_MD'],
    'amount'        => '12.34',
    'currency'      => 'TRY',
    'returnUrl'     => 'https://yoursite.com/payment/success',
    'cancelUrl'     => 'https://yoursite.com/payment/failure',
])->send();

if ($response->isSuccessful()) {
    echo "3D Payment successful!";
} else {
    echo "3D Payment failed: " . $response->getMessage();
}
```

### Onus Card Check (BIN)

```php
$response = $gateway->isOnusCard([
    'cardNumber' => '5400612345678901',
    'testMode'   => true,
])->send();

if ($response->isSuccessful() && $response->isOnus()) {
    // Show installment options for KuveytTurk-issued cards.
}
```

### Merchant Installment Definition

Returns the installment counts the merchant is allowed to offer. Rates are configured separately on the merchant side.

```php
$response = $gateway->installmentDefinition([
    'merchantId' => 'YOUR_MERCHANT_ID',
    'testMode'   => true,
])->send();

if ($response->isSuccessful()) {
    foreach ($response->getInstallments() as $row) {
        // Row shape depends on the API; usually contains the installment count and any limits.
        var_dump($row);
    }
}
```

### Cancel / Refund / Partial Refund (SaleReversal)

**İptal (cancel)** is a same-day cancellation of the whole sale. **İade (refund)** refunds from the following day on: omit `amount` for a full refund, pass it for a partial refund (Kısmi İade). Both are the same `/KTPay/SaleReversal` endpoint, addressed by the **bank-side** `orderId` (the `Result_OrderId` of the 3D callback) plus your `merchantOrderId` (passed as `transactionId`):

```php
$response = $gateway->cancel([
    'merchantId'    => 'YOUR_MERCHANT_ID',
    'customerId'    => 'YOUR_CUSTOMER_ID',
    'userName'      => 'YOUR_USERNAME',
    'password'      => 'YOUR_PASSWORD',
    'orderId'       => 203418448,    // bank-side order id (Result_OrderId)
    'transactionId' => 'ORDER-001',  // your merchant order id
])->send();

// Full refund (İade) — omit the amount.
$response = $gateway->refund([
    'merchantId'    => 'YOUR_MERCHANT_ID',
    'customerId'    => 'YOUR_CUSTOMER_ID',
    'userName'      => 'YOUR_USERNAME',
    'password'      => 'YOUR_PASSWORD',
    'orderId'       => 203418448,
    'transactionId' => 'ORDER-001',
])->send();

// Partial refund (Kısmi İade) — pass the amount in standard decimals.
$response = $gateway->refund([
    'merchantId'    => 'YOUR_MERCHANT_ID',
    'customerId'    => 'YOUR_CUSTOMER_ID',
    'userName'      => 'YOUR_USERNAME',
    'password'      => 'YOUR_PASSWORD',
    'orderId'       => 203418448,
    'transactionId' => 'ORDER-001',
    'amount'        => '12.34',
])->send();

if ($response->isSuccessful()) {
    echo $response->getTransactionReference(); // bank-side order id
} else {
    echo $response->getMessage();
}
```

### Transaction Lookup (GetTransactions)

Lists transactions for a merchant order id — useful to recover the bank-side `orderId` when it was not stored at payment time:

```php
$response = $gateway->fetchTransactions([
    'merchantId'    => 'YOUR_MERCHANT_ID',
    'customerId'    => 'YOUR_CUSTOMER_ID',
    'userName'      => 'YOUR_USERNAME',
    'password'      => 'YOUR_PASSWORD',
    'transactionId' => 'ORDER-001',
])->send();

foreach ($response->getTransactions() as $transaction) {
    echo $transaction->OrderId; // bank-side order id
}
```

## Supported Methods

| Method                    | Description                                                  |
|---------------------------|--------------------------------------------------------------|
| `purchase()`              | Non-3D or 3D sale (based on `secure` param)                  |
| `completePurchase()`      | Complete 3D payment after bank callback (provision)          |
| `isOnusCard()`            | Check whether a card is KuveytTurk-issued (Onus)             |
| `installmentDefinition()` | Fetch the merchant's allowed installment counts              |
| `cancel()`                | İptal — same-day cancellation (`/KTPay/SaleReversal`, type 3) |
| `refund()`                | İade / Kısmi İade — full refund (omit `amount`) or partial refund |
| `fetchTransactions()`     | List transactions (`/KTPay/GetTransactions`)                 |

## Currency Codes (4-digit, ISO 4217 numeric, zero-padded)

| Currency | Code |
|----------|------|
| TRY      | 0949 |
| USD      | 0840 |
| EUR      | 0978 |
| GBP      | 0826 |

## Amount Format

Amounts are passed as standard decimal values (e.g., `12.34`). The package internally converts them to the integer kuruş format required by KuveytTurk (e.g., `1234`).

`SaleReversal` operations always carry an amount: the official SDKs send `"0"` for cancellations and full refunds and the kuruş amount for partial refunds — this package does the same (`cancel()` and an `amount`-less `refund()` send `"0"`).

## Hash Algorithm

KTPay endpoints use **HMAC-SHA512** with the SHA1Base64-encoded password as the key.

```
hashPassword = Base64(SHA1(password))
message      = hashValue . hashPassword
hashData     = Base64(HMAC-SHA512(message, hashPassword))
```

Where `hashValue` is the field concatenation:

- **Non-3D / Provision:** `MerchantId + MerchantOrderId + Amount + UserName`
- **3D:** `MerchantId + MerchantOrderId + Amount + OkUrl + FailUrl + UserName`
- **SaleReversal:** `MerchantId + MerchantOrderId + Amount + UserName` (Amount is `0` for cancellations and full refunds, the kuruş amount for partial refunds; the bank-side `OrderId` is **not** part of the hash)
- **GetTransactions:** `MerchantId + MerchantOrderId + UserName`

## Endpoints

| Environment | Type                       | URL                                                                                                |
|-------------|----------------------------|-----------------------------------------------------------------------------------------------------|
| Test        | 3D Payment                 | https://boatest.kuveytturk.com.tr/boa.virtualpos.services/KTPay/Payment                            |
| Test        | 3D Provision               | https://boatest.kuveytturk.com.tr/boa.virtualpos.services/KTPay/Provision                          |
| Test        | Non-3D                     | https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/Non3DPayGate                        |
| Test        | Onus Card Check            | https://boatest.kuveytturk.com.tr/boa.virtualpos.services/KTPay/IsOnusCard                         |
| Test        | Installment Definition     | https://boatest.kuveytturk.com.tr/boa.virtualpos.services/KTPay/GetMerchantInstallmentDefinition   |
| Test        | Cancel / Refund            | https://boatest.kuveytturk.com.tr/boa.virtualpos.services/KTPay/SaleReversal                       |
| Test        | Transaction Lookup         | https://boatest.kuveytturk.com.tr/boa.virtualpos.services/KTPay/GetTransactions                     |
| Production  | 3D Payment                 | https://sanalpos.kuveytturk.com.tr/ServiceGateWay/KTPay/Payment                                    |
| Production  | 3D Provision               | https://sanalpos.kuveytturk.com.tr/ServiceGateWay/KTPay/Provision                                  |
| Production  | Non-3D                     | https://sanalpos.kuveytturk.com.tr/ServiceGateWay/Home/Non3DPayGate                                |
| Production  | Onus Card Check            | https://sanalpos.kuveytturk.com.tr/ServiceGateWay/KTPay/IsOnusCard                                 |
| Production  | Installment Definition     | https://sanalpos.kuveytturk.com.tr/ServiceGateWay/KTPay/GetMerchantInstallmentDefinition           |
| Production  | Cancel / Refund            | https://sanalpos.kuveytturk.com.tr/ServiceGateWay/KTPay/SaleReversal                                |
| Production  | Transaction Lookup         | https://sanalpos.kuveytturk.com.tr/ServiceGateWay/KTPay/GetTransactions                             |

## Upgrading from v2.x

| Change                              | Before (v2)                                       | After (v3)                                                |
|-------------------------------------|---------------------------------------------------|------------------------------------------------------------|
| 3D init endpoint                    | `/Home/ThreeDModelPayGate`                        | `/KTPay/Payment` (server-to-server JSON)                   |
| 3D provision endpoint               | `/Home/ThreeDModelProvisionGate`                  | `/KTPay/Provision`                                         |
| 3D init request format              | HTML form auto-submit (browser)                   | JSON, server-side; bank returns HTML to relay              |
| 3D callback payload                 | `AuthenticationResponse` (URL-encoded XML)        | `Result_MD`, `Result_OrderId`, `Result_MerchantOrderId`    |
| Hash algorithm                      | SHA1                                              | HMAC-SHA512 (key = SHA1Base64(password))                   |
| Currency code                       | `0949` (4-digit)                                  | `0949` (4-digit, unchanged)                                |
| `completePurchase()` parameters     | `authenticationResponse`, `md`                    | `transactionId`, `orderId`, `md`, `amount`, `currency`     |

Non-3D `/Home/Non3DPayGate` is still XML-based and still works, but uses the same HMAC-SHA512 hash and 3-digit currency codes.

## Testing

```bash
composer test
```

## License

MIT License. See [LICENSE](LICENSE) for details.
