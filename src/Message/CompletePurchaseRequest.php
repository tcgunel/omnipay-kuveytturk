<?php

namespace Omnipay\KuveytTurk\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\KuveytTurk\Helpers\Helper;
use Omnipay\KuveytTurk\Traits\PurchaseGettersSetters;

class CompletePurchaseRequest extends AbstractRequest
{
    use PurchaseGettersSetters;

    protected $test_endpoint = 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelProvisionGate';

    protected $prod_endpoint = 'https://sanalpos.kuveytturk.com.tr/ServiceGateWay/Home/ThreeDModelProvisionGate';

    /**
     * Get the AuthenticationResponse from the 3D callback.
     */
    public function getAuthenticationResponse()
    {
        return $this->getParameter('authenticationResponse');
    }

    public function setAuthenticationResponse($value)
    {
        return $this->setParameter('authenticationResponse', $value);
    }

    /**
     * Get the MD (Message Digest) from the 3D callback.
     */
    public function getMd()
    {
        return $this->getParameter('md');
    }

    public function setMd($value)
    {
        return $this->setParameter('md', $value);
    }

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
            'authenticationResponse',
        );

        $authResponse = $this->getAuthenticationResponse();

        // Decode the authentication response (it comes base64 encoded or URL encoded from the bank)
        $decodedResponse = base64_decode($authResponse);

        if ($decodedResponse === false || empty($decodedResponse)) {
            $decodedResponse = $authResponse;
        }

        $parsedAuth = Helper::xmlStringToObject($decodedResponse);

        // Check if 3D authentication was successful
        if (!isset($parsedAuth->ResponseCode) || $parsedAuth->ResponseCode !== '00') {
            return [
                '_authSuccess' => false,
                '_authResponse' => $parsedAuth,
                'ResponseCode' => $parsedAuth->ResponseCode ?? 'AuthFail',
                'ResponseMessage' => $parsedAuth->ResponseMessage ?? '3D authentication failed',
            ];
        }

        // Extract fields from the authentication response
        $merchantOrderId = $parsedAuth->MerchantOrderId ?? $this->getTransactionId() ?? '';
        $amount = $parsedAuth->VPosMessage->Amount ?? '0';

        $hash = Helper::hashNon3D(
            $this->getMerchantId(),
            (string) $merchantOrderId,
            (string) $amount,
            $this->getUserName(),
            $this->getPassword(),
        );

        $data = [
            'APIVersion' => 'TDV2.0.0',
            'MerchantId' => $this->getMerchantId(),
            'CustomerId' => $this->getCustomerId(),
            'UserName' => $this->getUserName(),
            'TransactionType' => 'Sale',
            'InstallmentCount' => (string) ($this->getInstallment() ?? 0),
            'Amount' => (string) $amount,
            'MerchantOrderId' => (string) $merchantOrderId,
            'TransactionSecurity' => '3',
            'KuveytTurkVPosAdditionalData' => [
                'AdditionalData' => [
                    'Key' => 'MD',
                    'Data' => $this->getMd() ?? ($parsedAuth->MD ?? ''),
                ],
            ],
            'HashData' => $hash,
            '_authSuccess' => true,
            '_authResponse' => $parsedAuth,
        ];

        return $data;
    }

    public function sendData($data)
    {
        // If 3D auth failed, return failure response without sending provision request
        if (empty($data['_authSuccess'])) {
            return $this->response = new CompletePurchaseResponse($this, $data, null);
        }

        // Remove internal keys before sending
        $sendData = $data;
        $authResponse = $sendData['_authResponse'] ?? null;
        unset($sendData['_authSuccess'], $sendData['_authResponse']);

        $xml = Helper::arrayToXml($sendData);

        $httpResponse = $this->httpClient->request(
            'POST',
            $this->getEndpoint(),
            [
                'Content-Type' => 'application/xml',
            ],
            $xml
        );

        return $this->response = new CompletePurchaseResponse(
            $this,
            $httpResponse->getBody()->getContents(),
            $authResponse
        );
    }

    public function getEndpoint(): string
    {
        return $this->getTestMode() ? $this->test_endpoint : $this->prod_endpoint;
    }
}
