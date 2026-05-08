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
        $this->is3D = $is3D;

        if (is_string($data)) {
            if ($is3D) {
                $trimmed = ltrim($data);

                if ($trimmed !== '' && $trimmed[0] === '{') {
                    $this->jsonError = Helper::jsonStringToObject($data);
                } else {
                    $this->rawHtml = $data;
                }
            } else {
                $this->parsedXml = Helper::xmlStringToObject($data);
            }
        }

        // Pass an array to the parent so that AbstractResponse::getData() returns array-shaped data.
        // Some consumers (e.g. logging layers that expect to walk a structure) break on raw strings.
        parent::__construct($request, $this->normalizeData($data));
    }

    /**
     * Build the array shape that getData() exposes.
     *
     * @param  mixed  $raw
     * @return array<string, mixed>
     */
    protected function normalizeData($raw): array
    {
        if ($this->is3D) {
            if ($this->jsonError !== null) {
                return (array) $this->jsonError;
            }

            if ($this->rawHtml !== null) {
                return ['html' => $this->rawHtml];
            }

            return [];
        }

        if ($this->parsedXml !== null) {
            return (array) $this->parsedXml;
        }

        return [];
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
     * Return the bank's HTML auto-submit form so the consumer can stream it to the browser.
     *
     * KuveytTurk's 3D Secure pages (and BKM's TROY redirect target) set X-Frame-Options: DENY,
     * which means any consumer that renders the returned HTML inside an iframe will hit
     * "bağlanmayı reddetti" once the bank tries to load its 3DS challenge page. Inject
     * target="_top" into every <form> tag (that doesn't already specify one) so the auto-submit
     * navigates the top-level window and breaks out of the iframe.
     */
    public function getRedirectResponse()
    {
        if (!$this->isRedirect()) {
            return new HttpFoundationResponse('');
        }

        return new HttpFoundationResponse($this->breakOutOfFrames($this->rawHtml ?? ''));
    }

    protected function breakOutOfFrames(string $html): string
    {
        if ($html === '') {
            return $html;
        }

        $patched = preg_replace(
            '/<form\b(?![^>]*\btarget=)/i',
            '<form target="_top"',
            $html,
        );

        return is_string($patched) ? $patched : $html;
    }
}
