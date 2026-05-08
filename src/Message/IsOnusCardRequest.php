<?php

namespace Omnipay\KuveytTurk\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Message\AbstractRequest;
use Omnipay\KuveytTurk\Traits\PurchaseGettersSetters;

/**
 * Onus card check at /KTPay/IsOnusCard.
 *
 * Tells you whether a card is issued by KuveytTurk (so installment offers can be shown).
 *
 * Request body matches the official WordPress plugin: {"cardNumber": "<PAN>"}.
 * The PAN can be the BIN (8+ digits) or the full card number — the plugin's JS sends the
 * full PAN once 8+ digits are entered. Pass either via setCardNumber() or setCardBin().
 */
class IsOnusCardRequest extends AbstractRequest
{
    use PurchaseGettersSetters;

    protected $test_endpoint = 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/KTPay/IsOnusCard';

    protected $prod_endpoint = 'https://sanalpos.kuveytturk.com.tr/ServiceGateWay/KTPay/IsOnusCard';

    public function getCardNumber()
    {
        return $this->getParameter('cardNumber') ?? $this->getCardBin();
    }

    public function setCardNumber($value)
    {
        return $this->setParameter('cardNumber', $value);
    }

    /**
     * @throws InvalidRequestException
     */
    public function getData()
    {
        $cardNumber = $this->getCardNumber();

        if (empty($cardNumber)) {
            throw new InvalidRequestException('The cardNumber (or cardBin) parameter is required');
        }

        return [
            'cardNumber' => (string) $cardNumber,
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

        return $this->response = new IsOnusCardResponse(
            $this,
            $httpResponse->getBody()->getContents(),
        );
    }

    public function getEndpoint(): string
    {
        return $this->getTestMode() ? $this->test_endpoint : $this->prod_endpoint;
    }
}
