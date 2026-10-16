<?php

namespace Omnipay\KuveytTurk\Message;

use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RequestInterface;
use Omnipay\KuveytTurk\Helpers\Helper;

/**
 * JSON response from /KTPay/SaleReversal (İptal / İade / Kısmi İade).
 * Success when ResponseCode === "00".
 *
 * SaleReversal nests the bank reference under Result
 * ({"Result":{"OrderId":…,"MerchantOrderId":…}}), so both the nested and the
 * flat shapes are read defensively.
 */
class SaleReversalResponse extends AbstractResponse
{
    protected ?object $parsedResponse = null;

    public function __construct(RequestInterface $request, $data)
    {
        if (is_string($data)) {
            $this->parsedResponse = Helper::jsonStringToObject($data);
        }

        // Pass an array to the parent so getData() always returns array-shaped data.
        $normalized = $this->parsedResponse !== null ? (array) $this->parsedResponse : [];

        parent::__construct($request, $normalized);
    }

    public function isSuccessful(): bool
    {
        return isset($this->parsedResponse->ResponseCode)
            && $this->parsedResponse->ResponseCode === '00';
    }

    public function getMessage(): ?string
    {
        return $this->parsedResponse->ResponseMessage ?? null;
    }

    public function getCode(): ?string
    {
        return $this->parsedResponse->ResponseCode ?? null;
    }

    public function getTransactionReference(): ?string
    {
        if ($this->parsedResponse === null) {
            return null;
        }

        $orderId = $this->parsedResponse->Result->OrderId
            ?? $this->parsedResponse->OrderId
            ?? null;

        if ($orderId !== null) {
            return (string) $orderId;
        }

        return isset($this->parsedResponse->MerchantOrderId)
            ? (string) $this->parsedResponse->MerchantOrderId
            : null;
    }

    public function getParsedResponse(): ?object
    {
        return $this->parsedResponse;
    }
}
