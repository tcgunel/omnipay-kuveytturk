<?php

namespace Omnipay\KuveytTurk\Message;

use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RequestInterface;
use Omnipay\KuveytTurk\Helpers\Helper;

class InstallmentDefinitionResponse extends AbstractResponse
{
    protected ?object $parsedResponse = null;

    public function __construct(RequestInterface $request, $data)
    {
        if (is_string($data)) {
            $this->parsedResponse = Helper::jsonStringToObject($data);
        }

        $normalized = $this->parsedResponse !== null ? (array) $this->parsedResponse : [];

        parent::__construct($request, $normalized);
    }

    public function isSuccessful(): bool
    {
        return isset($this->parsedResponse->success) && $this->parsedResponse->success === true;
    }

    /**
     * @return list<object>
     */
    public function getInstallments(): array
    {
        $value = $this->parsedResponse->value ?? null;

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_object'));
    }

    public function getMessage(): ?string
    {
        return $this->parsedResponse->message ?? null;
    }

    public function getParsedResponse(): ?object
    {
        return $this->parsedResponse;
    }
}
