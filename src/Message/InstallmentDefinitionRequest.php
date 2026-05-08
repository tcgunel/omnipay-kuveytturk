<?php

namespace Omnipay\KuveytTurk\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\KuveytTurk\Traits\PurchaseGettersSetters;

/**
 * Fetch the merchant's installment definition (allowed installment counts and rates) at
 * /KTPay/GetMerchantInstallmentDefinition.
 *
 * Required parameter: merchantId.
 */
class InstallmentDefinitionRequest extends AbstractRequest
{
    use PurchaseGettersSetters;

    protected $test_endpoint = 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/KTPay/GetMerchantInstallmentDefinition';

    protected $prod_endpoint = 'https://sanalpos.kuveytturk.com.tr/ServiceGateWay/KTPay/GetMerchantInstallmentDefinition';

    /**
     * @throws InvalidRequestException
     */
    public function getData()
    {
        $this->validate('merchantId');

        return [
            'merchantId' => (int) $this->getMerchantId(),
        ];
    }

    public function sendData($data)
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR);

        $httpResponse = $this->httpClient->request(
            'POST',
            $this->getEndpoint(),
            [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            $body,
        );

        return $this->response = new InstallmentDefinitionResponse(
            $this,
            $httpResponse->getBody()->getContents(),
        );
    }

    public function getEndpoint(): string
    {
        return $this->getTestMode() ? $this->test_endpoint : $this->prod_endpoint;
    }
}
