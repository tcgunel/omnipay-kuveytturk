<?php

namespace Omnipay\KuveytTurk\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\KuveytTurk\Helpers\Helper;
use Omnipay\KuveytTurk\Traits\PurchaseGettersSetters;

/**
 * After the customer completes 3DS, the bank POSTs to the merchant's successUrl/failUrl with:
 *   - Result_MD            -> set as `md`
 *   - Result_OrderId       -> set as `orderId`
 *   - Result_MerchantOrderId -> set as `transactionId`
 *   - ResponseMessage      -> failure reason on the failUrl path
 *
 * Pass these alongside the original amount (Omnipay decimal) to capture the funds via /KTPay/Provision.
 */
class CompletePurchaseRequest extends AbstractRequest
{
    use PurchaseGettersSetters;

    protected $test_endpoint = 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/KTPay/Provision';

    protected $prod_endpoint = 'https://sanalpos.kuveytturk.com.tr/ServiceGateWay/KTPay/Provision';

    /**
     * @throws InvalidRequestException
     */
    public function getData()
    {
        $this->validate(
            'merchantId',
            'customerId',
            'userName',
            'password',
            'transactionId',
            'amount',
            'md',
            'orderId',
        );

        $merchantOrderId = (string) $this->getTransactionId();
        $amount = Helper::formatAmount($this->getAmountInteger());

        $hash = Helper::hashNon3D(
            (string) $this->getMerchantId(),
            $merchantOrderId,
            $amount,
            (string) $this->getUserName(),
            (string) $this->getPassword(),
        );

        // IDs are sent as strings here to match the official KuveytTurk WordPress plugin exactly.
        return [
            'language' => 1,
            'merchantId' => (string) $this->getMerchantId(),
            'customerId' => (string) $this->getCustomerId(),
            'username' => (string) $this->getUserName(),
            'hashData' => $hash,
            'amount' => $amount,
            'orderId' => (string) $this->getOrderId(),
            'merchantOrderId' => $merchantOrderId,
            'successUrl' => (string) ($this->getReturnUrl() ?? ''),
            'failUrl' => (string) ($this->getCancelUrl() ?? ''),
            'md' => (string) $this->getMd(),
        ];
    }

    public function sendData($data)
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $httpResponse = $this->httpClient->request(
            'POST',
            $this->getEndpoint(),
            [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            $body,
        );

        return $this->response = new CompletePurchaseResponse(
            $this,
            $httpResponse->getBody()->getContents(),
        );
    }

    public function getEndpoint(): string
    {
        return $this->getTestMode() ? $this->test_endpoint : $this->prod_endpoint;
    }
}
