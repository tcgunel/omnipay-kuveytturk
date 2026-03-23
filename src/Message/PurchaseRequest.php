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

    protected $test_endpoint_non3d = 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/Non3DPayGate';

    protected $test_endpoint_3d = 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelPayGate';

    protected $prod_endpoint_non3d = 'https://sanalpos.kuveytturk.com.tr/ServiceGateWay/Home/Non3DPayGate';

    protected $prod_endpoint_3d = 'https://sanalpos.kuveytturk.com.tr/ServiceGateWay/Home/ThreeDModelPayGate';

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

        $amount = Helper::formatAmount($this->getAmountInteger());
        $currencyCode = CurrencyCode::fromAlpha($this->getCurrency());
        $installment = $this->getInstallment() ?? 0;
        $secure = $this->getSecure();

        if ($secure) {
            $this->validate('returnUrl', 'cancelUrl');

            $hash = Helper::hash3D(
                $this->getMerchantId(),
                $this->getTransactionId(),
                $amount,
                $this->getReturnUrl(),
                $this->getCancelUrl(),
                $this->getUserName(),
                $this->getPassword(),
            );
        } else {
            $hash = Helper::hashNon3D(
                $this->getMerchantId(),
                $this->getTransactionId(),
                $amount,
                $this->getUserName(),
                $this->getPassword(),
            );
        }

        $data = [
            'APIVersion' => 'TDV2.0.0',
            'CardNumber' => $this->getCard()->getNumber(),
            'CardExpireDateYear' => substr($this->getCard()->getExpiryYear(), -2),
            'CardExpireDateMonth' => str_pad($this->getCard()->getExpiryMonth(), 2, '0', STR_PAD_LEFT),
            'CardCVV2' => $this->getCard()->getCvv(),
            'CardHolderName' => $this->getCard()->getName(),
            'BatchID' => '0',
            'TransactionType' => TransactionType::SALE,
            'InstallmentCount' => (string) $installment,
            'Amount' => $amount,
            'DisplayAmount' => $amount,
            'CurrencyCode' => $currencyCode,
            'MerchantOrderId' => $this->getTransactionId(),
            'TransactionSecurity' => $secure ? '3' : '1',
        ];

        if ($secure) {
            $data['OkUrl'] = $this->getReturnUrl();
            $data['FailUrl'] = $this->getCancelUrl();
        }

        $data['UserName'] = $this->getUserName();
        $data['MerchantId'] = $this->getMerchantId();
        $data['CustomerId'] = $this->getCustomerId();
        $data['HashData'] = $hash;

        return $data;
    }

    public function sendData($data)
    {
        if ($this->getSecure()) {
            return $this->response = new PurchaseResponse($this, $data);
        }

        $xml = Helper::arrayToXml($data);

        $httpResponse = $this->httpClient->request(
            'POST',
            $this->getEndpoint(),
            [
                'Content-Type' => 'application/xml',
            ],
            $xml
        );

        return $this->response = new PurchaseResponse($this, $httpResponse->getBody()->getContents(), false);
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
