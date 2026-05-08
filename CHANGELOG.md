# Changelog

All notable changes to `tcgunel/omnipay-kuveytturk` are documented here.

This project follows [Semantic Versioning](https://semver.org/).

## [3.0.1] — 2026-05-08

### Fixed

- Currency codes reverted to 4-digit ISO 4217 numeric, zero-padded (`0949`/`0840`/`0978`/`0826`). The official WordPress plugin sends 4-digit codes via its `KTPayConfig::get_currency_code()` helper. Sending 3-digit codes caused the bank to reject 3D Secure requests with `RequestValidationError` / `CurrencyValidationError`.

## [3.0.0] — 2026-05-08

### Breaking changes

- 3D Secure flow migrated from the legacy `Home/ThreeDModelPayGate` HTML-form flow to the new `KTPay/Payment` JSON flow. The package now performs a server-to-server `POST application/json` and relays the bank's HTML auto-submit form to the browser via `getRedirectResponse()->getContent()`.
- 3D provision endpoint changed from `/Home/ThreeDModelProvisionGate` (XML) to `/KTPay/Provision` (JSON).
- 3D callback parameters: `CompletePurchaseRequest` now reads `transactionId` (= `Result_MerchantOrderId`), `orderId` (= `Result_OrderId`), and `md` (= `Result_MD`) instead of `authenticationResponse`.
- Hash algorithm changed from SHA1 to HMAC-SHA512 keyed by `Base64(SHA1(password))` for all KTPay endpoints (and the Non-3D endpoint, to match the official WordPress plugin).
- `Helpers\Helper::hash3D` / `hashNon3D` signatures unchanged but their internal algorithm is now HMAC-SHA512.

### Added

- `Gateway::isOnusCard()` — `POST /KTPay/IsOnusCard` to check whether a card is KuveytTurk-issued.
- `Gateway::installmentDefinition()` — `POST /KTPay/GetMerchantInstallmentDefinition` to fetch the merchant's allowed installment counts.
- `Helpers\Helper::hashKTPay()` — public HMAC-SHA512 hash helper.
- `Helpers\Helper::jsonStringToObject()` — defensive JSON parser used across responses.
- `Helpers\Helper::trSubscriber()` — strips `+90` / `90` / `0` prefixes from Turkish phone numbers and returns the 10-digit subscriber portion.
- `cardBin` / `md` / `cardNumber` getters/setters on the shared trait.

### Removed

- `CompletePurchaseRequest::setAuthenticationResponse()` and the legacy XML provision body builder. Use the new `Result_*` callback fields instead.
- Legacy SHA1 hash code path.
- Mock fixtures for the legacy XML auth response flow.

## [2.0.0]

- Last release on the legacy `Home/ThreeDModelPayGate` flow with SHA1 hashing.

## [1.0.0]

- Initial release.
