<?php

namespace Omnipay\KuveytTurk\Message;

use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RedirectResponseInterface;
use Omnipay\Common\Message\RequestInterface;
use Omnipay\KuveytTurk\Helpers\Helper;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

/**
 * 3D flow:
 *   - Server POSTs JSON to /KTPay/Payment.
 *   - Bank returns either:
 *       * an HTML auto-submit form pointing at the bank's 3D Secure page (success path), or
 *       * a JSON object describing the error (failure path).
 *   - On HTML: isRedirect() === true; getRedirectResponse() returns the bank's HTML to be sent to the browser.
 *   - On JSON: isRedirect() === false, isSuccessful() === false, getMessage()/getCode() expose the error.
 *
 * Non-3D flow:
 *   - XML response from /Home/Non3DPayGate; isSuccessful() based on ResponseCode.
 */
class PurchaseResponse extends AbstractResponse implements RedirectResponseInterface
{
    protected bool $is3D;

    protected ?string $rawHtml = null;

    protected ?object $jsonError = null;

    protected ?object $parsedXml = null;

    public function __construct(RequestInterface $request, $data, bool $is3D = true)
    {
        parent::__construct($request, $data);

        $this->is3D = $is3D;

        if (!is_string($data)) {
            return;
        }

        if ($is3D) {
            $trimmed = ltrim($data);

            if ($trimmed !== '' && $trimmed[0] === '{') {
                $this->jsonError = Helper::jsonStringToObject($data);
            } else {
                $this->rawHtml = $data;
            }

            return;
        }

        $this->parsedXml = Helper::xmlStringToObject($data);
    }

    public function isSuccessful(): bool
    {
        if ($this->is3D) {
            return false;
        }

        return isset($this->parsedXml->ResponseCode)
            && $this->parsedXml->ResponseCode === '00';
    }

    public function isRedirect(): bool
    {
        return $this->is3D && $this->rawHtml !== null;
    }

    public function getRedirectUrl()
    {
        // The bank returned the 3DS form HTML directly (see getRedirectResponse).
        // There is no separate URL for the framework to redirect to.
        return '';
    }

    public function getRedirectMethod(): string
    {
        return 'POST';
    }

    public function getRedirectData(): array
    {
        return [];
    }

    public function getMessage(): ?string
    {
        if ($this->is3D) {
            return $this->jsonError->ResponseMessage ?? null;
        }

        return $this->parsedXml->ResponseMessage ?? null;
    }

    public function getCode(): ?string
    {
        if ($this->is3D) {
            return $this->jsonError->ResponseCode ?? null;
        }

        return $this->parsedXml->ResponseCode ?? null;
    }

    public function getParsedResponse(): ?object
    {
        return $this->is3D ? $this->jsonError : $this->parsedXml;
    }

    public function getRawHtml(): ?string
    {
        return $this->rawHtml;
    }

    /**
     * Return the bank's HTML auto-submit form as-is so the consumer can stream it to the browser.
     */
    public function getRedirectResponse()
    {
        if (!$this->isRedirect()) {
            return new HttpFoundationResponse('');
        }

        return new HttpFoundationResponse($this->rawHtml ?? '');
    }
}
