<?php

namespace Omnipay\KuveytTurk;

use Omnipay\Common\AbstractGateway;
use Omnipay\KuveytTurk\Message\CancelRequest;
use Omnipay\KuveytTurk\Message\CompletePurchaseRequest;
use Omnipay\KuveytTurk\Message\FetchTransactionsRequest;
use Omnipay\KuveytTurk\Message\InstallmentDefinitionRequest;
use Omnipay\KuveytTurk\Message\IsOnusCardRequest;
use Omnipay\KuveytTurk\Message\PurchaseRequest;
use Omnipay\KuveytTurk\Message\RefundRequest;
use Omnipay\KuveytTurk\Traits\PurchaseGettersSetters;

/**
 * KuveytTurk Gateway
 * (c) Tolga Can Gunel
 * 2015, mobius.studio
 * http://www.github.com/tcgunel/omnipay-kuveytturk
 * @method \Omnipay\Common\Message\NotificationInterface acceptNotification(array $options = [])
 * @method \Omnipay\Common\Message\RequestInterface completeAuthorize(array $options = [])
 */
class Gateway extends AbstractGateway
{
    use PurchaseGettersSetters;

    public function getName(): string
    {
        return 'KuveytTurk';
    }

    public function getDefaultParameters()
    {
        return [
            'clientIp' => '127.0.0.1',
            'merchantId' => '',
            'customerId' => '',
            'userName' => '',
            'password' => '',
            'installment' => 0,
            'secure' => true,
        ];
    }

    public function purchase(array $options = [])
    {
        return $this->createRequest(PurchaseRequest::class, $options);
    }

    public function completePurchase(array $options = [])
    {
        return $this->createRequest(CompletePurchaseRequest::class, $options);
    }

    public function isOnusCard(array $options = [])
    {
        return $this->createRequest(IsOnusCardRequest::class, $options);
    }

    public function installmentDefinition(array $options = [])
    {
        return $this->createRequest(InstallmentDefinitionRequest::class, $options);
    }

    /**
     * İptal — same-day cancellation of the whole sale (/KTPay/SaleReversal).
     */
    public function cancel(array $options = [])
    {
        return $this->createRequest(CancelRequest::class, $options);
    }

    /**
     * İade / Kısmi İade — full or partial refund (/KTPay/SaleReversal). Omit
     * `amount` for a full refund; a given amount makes it a partial refund.
     */
    public function refund(array $options = [])
    {
        return $this->createRequest(RefundRequest::class, $options);
    }

    /**
     * List transactions for a merchant order id (/KTPay/GetTransactions).
     */
    public function fetchTransactions(array $options = [])
    {
        return $this->createRequest(FetchTransactionsRequest::class, $options);
    }
}
