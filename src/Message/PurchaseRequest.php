<?php

namespace Omnipay\KuveytTurk\Message;

use Omnipay\Common\Exception\InvalidCreditCardException;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\KuveytTurk\Constants\CurrencyCode;
use Omnipay\KuveytTurk\Constants\TransactionType;
use Omnipay\KuveytTurk\Helpers\Helper;
use Omnipay\KuveytTurk\Traits\PurchaseGettersSetters;

class PurchaseRequest extends AbstractRequest
{
    use PurchaseGettersSetters;

    protected $test_endpoint_3d = 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/KTPay/Payment';

    protected $prod_endpoint_3d = 'https://sanalpos.kuveytturk.com.tr/ServiceGateWay/KTPay/Payment';

    protected $test_endpoint_non3d = 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/Non3DPayGate';

    protected $prod_endpoint_non3d = 'https://sanalpos.kuveytturk.com.tr/ServiceGateWay/Home/Non3DPayGate';

    /**
     * @throws InvalidRequestException
     * @throws InvalidCreditCardException
     */
    public function getData()
    {
        $this->validate(
            'merchantId',
            'customerId',
            'userName',
            'password',
            'amount',
            'currency',
            'transactionId',
            'card',
        );

        $this->getCard()->validate();

        return $this->getSecure()
            ? $this->build3DJsonData()
            : $this->buildNon3DXmlData();
    }

    /**
     * Build the JSON body for the new /KTPay/Payment 3D flow.
     *
     * @return array<string, mixed>
     */
    protected function build3DJsonData(): array
    {
        $this->validate('returnUrl', 'cancelUrl');

        $amount = Helper::formatAmount($this->getAmountInteger());
        $merchantOrderId = (string) $this->getTransactionId();
        $okUrl = (string) $this->getReturnUrl();
        $failUrl = (string) $this->getCancelUrl();

        $hash = Helper::hash3D(
            (string) $this->getMerchantId(),
            $merchantOrderId,
            $amount,
            $okUrl,
            $failUrl,
            (string) $this->getUserName(),
            (string) $this->getPassword(),
        );

        $card = $this->getCard();

        // Customer key order matches the official WP plugin: phoneNumber, email, ipAddress.
        $customer = [];

        if ($subscriber = Helper::trSubscriber($card->getPhone())) {
            $customer['phoneNumber'] = [
                'cc' => '90',
                'subscriber' => $subscriber,
            ];
        }

        if ($email = $card->getEmail()) {
            $customer['email'] = $email;
        }

        $customer['ipAddress'] = $this->getClientIp() ?? '127.0.0.1';

        return [
            'paymentType' => 1,
            'language' => 1,
            'merchantOrderId' => $merchantOrderId,
            'successUrl' => $okUrl,
            'failUrl' => $failUrl,
            'merchantId' => (int) $this->getMerchantId(),
            'customerId' => (int) $this->getCustomerId(),
            'username' => (string) $this->getUserName(),
            'hashData' => $hash,
            'amount' => $amount,
            'currency' => CurrencyCode::fromAlpha($this->getCurrency()),
            'installmentCount' => (int) ($this->getInstallment() ?? 0),
            'customer' => $customer,
            'card' => [
                'cardNumber' => $card->getNumber(),
                'cardHolderName' => $card->getName(),
                'expireMonth' => str_pad((string) $card->getExpiryMonth(), 2, '0', STR_PAD_LEFT),
                'expireYear' => substr((string) $card->getExpiryYear(), -2),
                'securityCode' => $card->getCvv(),
            ],
        ];
    }

    /**
     * Build the legacy XML body for /Home/Non3DPayGate (still in use for non-3D).
     *
     * @return array<string, mixed>
     */
    protected function buildNon3DXmlData(): array
    {
        $amount = Helper::formatAmount($this->getAmountInteger());
        $merchantOrderId = (string) $this->getTransactionId();

        $hash = Helper::hashNon3D(
            (string) $this->getMerchantId(),
            $merchantOrderId,
            $amount,
            (string) $this->getUserName(),
            (string) $this->getPassword(),
        );

        // Element order matches the official KuveytTurk WordPress plugin's init_non3d_request_body().
        return [
            'HashData' => $hash,
            'MerchantId' => $this->getMerchantId(),
            'CustomerId' => $this->getCustomerId(),
            'UserName' => $this->getUserName(),
            'CardNumber' => $this->getCard()->getNumber(),
            'CardExpireDateYear' => substr((string) $this->getCard()->getExpiryYear(), -2),
            'CardExpireDateMonth' => str_pad((string) $this->getCard()->getExpiryMonth(), 2, '0', STR_PAD_LEFT),
            'CardCVV2' => $this->getCard()->getCvv(),
            'CardHolderName' => $this->getCard()->getName(),
            'BatchID' => '0',
            'TransactionType' => TransactionType::SALE,
            'InstallmentCount' => (string) ($this->getInstallment() ?? 0),
            'InstallmentMaturityCommisionFlag' => '0',
            'Amount' => $amount,
            'DisplayAmount' => $amount,
            'CurrencyCode' => CurrencyCode::fromAlpha($this->getCurrency()),
            'MerchantOrderId' => $merchantOrderId,
            'TransactionSecurity' => '1',
            'TransactionSide' => 'Sale',
        ];
    }

    public function sendData($data)
    {
        if ($this->getSecure()) {
            $body = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $httpResponse = $this->httpClient->request(
                'POST',
                $this->getEndpoint(),
                [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json, text/html',
                ],
                $body,
            );

            return $this->response = new PurchaseResponse(
                $this,
                $httpResponse->getBody()->getContents(),
                true,
            );
        }

        $xml = Helper::arrayToXml($data);

        $httpResponse = $this->httpClient->request(
            'POST',
            $this->getEndpoint(),
            [
                'Content-Type' => 'application/xml',
            ],
            $xml,
        );

        return $this->response = new PurchaseResponse(
            $this,
            $httpResponse->getBody()->getContents(),
            false,
        );
    }

    public function getEndpoint(): string
    {
        $secure = $this->getSecure();

        if ($this->getTestMode()) {
            return $secure ? $this->test_endpoint_3d : $this->test_endpoint_non3d;
        }

        return $secure ? $this->prod_endpoint_3d : $this->prod_endpoint_non3d;
    }
}
