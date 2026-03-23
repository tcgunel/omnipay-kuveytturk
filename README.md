# Omnipay: KuveytTurk

**KuveytTurk sanal pos gateway for the Omnipay PHP payment processing library.**

[Omnipay](https://github.com/thephpleague/omnipay) is a framework agnostic, multi-gateway payment
processing library for PHP. This package implements KuveytTurk (Kuveyt Turk Bankasi) support for Omnipay.

## Installation

```bash
composer require tcgunel/omnipay-kuveytturk
```

## Usage

### Gateway Parameters

| Parameter    | Description                          |
|-------------|--------------------------------------|
| `merchantId` | Merchant ID (MerchantId)            |
| `customerId` | Customer ID (CustomerId)            |
| `userName`   | API Username (UserName)             |
| `password`   | API Password (plain text, hashed internally) |
| `installment`| Installment count (0 = no installment) |
| `secure`     | Use 3D Secure (true/false)          |

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

**Step 1: Redirect to bank**

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
    ],
])->send();

if ($response->isRedirect()) {
    $response->redirect(); // Redirects to bank 3D page
}
```

**Step 2: Complete purchase (on callback)**

```php
$response = $gateway->completePurchase([
    'merchantId'             => 'YOUR_MERCHANT_ID',
    'customerId'             => 'YOUR_CUSTOMER_ID',
    'userName'               => 'YOUR_USERNAME',
    'password'               => 'YOUR_PASSWORD',
    'authenticationResponse' => $_POST['AuthenticationResponse'], // Base64 encoded XML from bank
    'md'                     => $_POST['MD'], // Message Digest from bank callback
    'installment'            => 0,
])->send();

if ($response->isSuccessful()) {
    echo "3D Payment successful!";
} else {
    echo "3D Payment failed: " . $response->getMessage();
}
```

## Supported Methods

| Method             | Description                       |
|-------------------|-----------------------------------|
| `purchase()`       | Non-3D or 3D sale (based on `secure` param) |
| `completePurchase()` | Complete 3D payment after bank callback |

## Currency Codes

| Currency | Code  |
|----------|-------|
| TRY      | 0949  |
| USD      | 0840  |
| EUR      | 0978  |
| GBP      | 0826  |

## Amount Format

Amounts are passed as standard decimal values (e.g., `12.34`). The gateway internally converts them to integer format required by KuveytTurk (e.g., `1234`).

## Hash Algorithm

- **Non-3D:** `SHA1Base64(MerchantId + MerchantOrderId + Amount + UserName + SHA1Base64(Password))`
- **3D:** `SHA1Base64(MerchantId + MerchantOrderId + Amount + OkUrl + FailUrl + UserName + SHA1Base64(Password))`

## Endpoints

| Environment | Type            | URL                                                                                   |
|------------|-----------------|----------------------------------------------------------------------------------------|
| Test       | Non-3D          | https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/Non3DPayGate           |
| Test       | 3D Pay          | https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelPayGate     |
| Test       | 3D Provision    | https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelProvisionGate |
| Production | Non-3D          | https://sanalpos.kuveytturk.com.tr/ServiceGateWay/Home/Non3DPayGate                   |
| Production | 3D Pay          | https://sanalpos.kuveytturk.com.tr/ServiceGateWay/Home/ThreeDModelPayGate             |
| Production | 3D Provision    | https://sanalpos.kuveytturk.com.tr/ServiceGateWay/Home/ThreeDModelProvisionGate       |

## Not Implemented

Cancel and Refund operations are not implemented (the CP.VPOS reference throws `NotImplementedException` for these operations).

## Testing

```bash
composer test
```

## License

MIT License. See [LICENSE](LICENSE) for details.
