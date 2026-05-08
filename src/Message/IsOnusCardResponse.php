<?php

namespace Omnipay\KuveytTurk\Message;

use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RequestInterface;
use Omnipay\KuveytTurk\Helpers\Helper;

/**
 * Response shape matches the official WordPress plugin: {"success": true|false, "value": <truthy|falsy>}.
 *
 * `value` is treated as truthy = "Onus card", falsy = "not Onus" (the WP plugin uses a JS truthy check).
 * If a future API version returns a richer object under `value`, getValue() returns the raw value
 * untouched so callers can inspect it.
 */
class IsOnusCardResponse extends AbstractResponse
{
    protected ?object $parsedResponse = null;

    public function __construct(RequestInterface $request, $data)
    {
        if (is_string($data)) {
            $this->parsedResponse = Helper::jsonStringToObject($data);
        }

        $normalized = $this->parsedResponse !== null ? (array) $this->parsedResponse : [];

        parent::__construct($request, $normalized);
    }

    public function isSuccessful(): bool
    {
        return isset($this->parsedResponse->success) && $this->parsedResponse->success === true;
    }

    public function isOnus(): bool
    {
        if (!$this->isSuccessful()) {
            return false;
        }

        $value = $this->parsedResponse->value ?? null;

        if (is_object($value) && isset($value->isOnus)) {
            return (bool) $value->isOnus;
        }

        return (bool) $value;
    }

    public function getValue()
    {
        return $this->parsedResponse->value ?? null;
    }

    public function getMessage(): ?string
    {
        return $this->parsedResponse->message ?? null;
    }

    public function getParsedResponse(): ?object
    {
        return $this->parsedResponse;
    }
}
