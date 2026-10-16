<?php

namespace Omnipay\KuveytTurk\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\KuveytTurk\Helpers\Helper;
use Omnipay\KuveytTurk\Traits\PurchaseGettersSetters;

/**
 * /KTPay/GetTransactions — transaction listing, used to look a sale up at the
 * bank (e.g. to recover the bank-side OrderId that SaleReversal needs when the
 * order was paid before that id was persisted).
 *
 * All filters are optional per the official SDKs; `merchantOrderId` (passed as
 * `transactionId`, like every other request) identifies the sale. The hash
 * covers MerchantId + MerchantOrderId + UserName — no amount.
 */
class FetchTransactionsRequest extends AbstractRequest
{
    use PurchaseGettersSetters;

    protected $test_endpoint = 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/KTPay/GetTransactions';

    protected $prod_endpoint = 'https://sanalpos.kuveytturk.com.tr/ServiceGateWay/KTPay/GetTransactions';

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
        );

        $merchantOrderId = (string) $this->getTransactionId();

        $hash = Helper::hashFetchTransactions(
            (string) $this->getMerchantId(),
            $merchantOrderId,
            (string) $this->getUserName(),
            (string) $this->getPassword(),
        );

        $data = [
            'language' => 1,
            'merchantId' => (string) $this->getMerchantId(),
            'customerId' => (string) $this->getCustomerId(),
            'username' => (string) $this->getUserName(),
            'hashData' => $hash,
            'merchantOrderId' => $merchantOrderId,
        ];

        if ($orderId = $this->getOrderId()) {
            $data['orderId'] = (int) $orderId;
        }

        if ($startDate = $this->getStartDate()) {
            $data['startDate'] = (string) $startDate;
        }

        if ($endDate = $this->getEndDate()) {
            $data['endDate'] = (string) $endDate;
        }

        return $data;
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

        return $this->response = new FetchTransactionsResponse(
            $this,
            $httpResponse->getBody()->getContents(),
        );
    }

    public function getEndpoint(): string
    {
        return $this->getTestMode() ? $this->test_endpoint : $this->prod_endpoint;
    }

    /**
     * Optional filter: start of the transaction date range ("Y-m-d H:i:s").
     */
    public function setStartDate($value)
    {
        return $this->setParameter('startDate', $value);
    }

    public function getStartDate()
    {
        return $this->getParameter('startDate');
    }

    /**
     * Optional filter: end of the transaction date range ("Y-m-d H:i:s").
     */
    public function setEndDate($value)
    {
        return $this->setParameter('endDate', $value);
    }

    public function getEndDate()
    {
        return $this->getParameter('endDate');
    }
}
