<?php

namespace Omnipay\KuveytTurk\Message;

use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RequestInterface;
use Omnipay\KuveytTurk\Helpers\Helper;

/**
 * JSON response from /KTPay/GetTransactions.
 *
 * Success responses carry ResponseCode "Successfull" (sic) alongside
 * Success=true — unlike the payment endpoints' "00" — so both markers are
 * accepted. Transaction rows live under Result.Transactions (raw bank shape,
 * including OrderId, MerchantOrderId and the IsCancellable/IsRefundable flags).
 */
class FetchTransactionsResponse extends AbstractResponse
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
        if ($this->parsedResponse === null) {
            return false;
        }

        if (($this->parsedResponse->Success ?? null) === true) {
            return true;
        }

        return isset($this->parsedResponse->ResponseCode)
            && in_array($this->parsedResponse->ResponseCode, ['00', 'Successfull'], true);
    }

    public function getMessage(): ?string
    {
        return $this->parsedResponse->ResponseMessage ?? null;
    }

    public function getCode(): ?string
    {
        $code = $this->parsedResponse->ResponseCode ?? null;

        return is_scalar($code) ? (string) $code : null;
    }

    /**
     * @return array<int, object> Raw transaction rows as returned by the bank.
     */
    public function getTransactions(): array
    {
        $transactions = $this->parsedResponse->Result->Transactions ?? [];

        return is_array($transactions) ? $transactions : [];
    }

    public function getParsedResponse(): ?object
    {
        return $this->parsedResponse;
    }
}
