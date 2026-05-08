# Changelog

All notable changes to `tcgunel/omnipay-kuveytturk` are documented here.

This project follows [Semantic Versioning](https://semver.org/).

## [3.0.3] — 2026-05-08

### Fixed

- `PurchaseResponse::getRedirectResponse()` now injects `target="_top"` into every `<form>` in the bank's HTML auto-submit form (forms that already have a target attribute are left alone). KuveytTurk's 3D Secure pages and BKM's TROY redirect target send `X-Frame-Options: DENY`, so consumers that render the returned HTML inside an iframe used to fail with "bağlanmayı reddetti" the moment the bank tried to load its 3DS challenge page. The injected `target="_top"` makes the auto-submit break out of any embedding iframe.

## [3.0.2] — 2026-05-08

### Fixed

- All response classes now expose array-shaped data via `getData()`. Previously `PurchaseResponse` and friends passed raw response strings (XML / HTML / JSON) to `AbstractResponse`'s constructor, which made `getData()` return a string. Some downstream logging layers walk this structure and break on strings (e.g. `foreach` over a string in PHP 8 throws `TypeError`), causing the post-redirect log row to never be persisted. Parsed objects are now cast to arrays, and raw HTML is wrapped as `['html' => …]`. No public method signatures change.

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
