<?php

namespace Omnipay\KuveytTurk\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\KuveytTurk\Helpers\Helper;
use Omnipay\KuveytTurk\Traits\PurchaseGettersSetters;

/**
 * Shared base for /KTPay/SaleReversal operations (İptal / İade / Kısmi İade),
 * mirroring the official KuveytTurk SDKs (ktpay-php, ktpay-dotnet) and the
 * official Postman collection:
 *
 *   - the bank-side orderId (İşlem Numarası) is what addresses the sale — it
 *     is returned by the 3D callback as Result_OrderId and is NOT part of the
 *     hash;
 *   - merchantId / customerId / username are sent as strings (the official
 *     Postman collection does the same), orderId as a number (all official
 *     samples);
 *   - amount is the kuruş string and is always present: "0" for cancellations
 *     and full refunds, the refunded amount for partial refunds.
 */
abstract class AbstractSaleReversalRequest extends AbstractRequest
{
    use PurchaseGettersSetters;

    protected $test_endpoint = 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/KTPay/SaleReversal';

    protected $prod_endpoint = 'https://sanalpos.kuveytturk.com.tr/ServiceGateWay/KTPay/SaleReversal';

    /**
     * KTPay SaleReversalType (see Constants\SaleReversalType).
     */
    abstract protected function getSaleReversalType(): int;

    /**
     * Amount sent in the body and used in the hash: "0" for cancellations and
     * full refunds, the kuruş amount for a partial refund.
     */
    abstract protected function getReversalAmount(): string;

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
            'orderId',
            'transactionId',
        );

        $merchantOrderId = (string) $this->getTransactionId();
        $amount = $this->getReversalAmount();

        $hash = Helper::hashSaleReversal(
            (string) $this->getMerchantId(),
            $merchantOrderId,
            $amount,
            (string) $this->getUserName(),
            (string) $this->getPassword(),
        );

        return [
            'language' => 1,
            'saleReversalType' => $this->getSaleReversalType(),
            'merchantId' => (string) $this->getMerchantId(),
            'customerId' => (string) $this->getCustomerId(),
            'username' => (string) $this->getUserName(),
            'hashData' => $hash,
            'orderId' => (int) $this->getOrderId(),
            'merchantOrderId' => $merchantOrderId,
            'amount' => $amount,
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

        return $this->response = new SaleReversalResponse(
            $this,
            $httpResponse->getBody()->getContents(),
        );
    }

    public function getEndpoint(): string
    {
        return $this->getTestMode() ? $this->test_endpoint : $this->prod_endpoint;
    }
}
