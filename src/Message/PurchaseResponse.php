<?php

namespace Omnipay\KuveytTurk\Message;

use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RedirectResponseInterface;
use Omnipay\Common\Message\RequestInterface;
use Omnipay\KuveytTurk\Helpers\Helper;

/**
 * KuveytTurk Purchase Response
 *
 * For 3D secure: redirects to bank page (isRedirect = true, isSuccessful = false)
 * For Non-3D: contains XML response (isSuccessful based on ResponseCode)
 */
class PurchaseResponse extends AbstractResponse implements RedirectResponseInterface
{
    protected bool $is3D;

    protected ?object $parsedResponse = null;

    /**
     * @param RequestInterface $request
     * @param mixed $data - array for 3D (redirect data), string for non-3D (XML response)
     * @param bool $is3D
     */
    public function __construct(RequestInterface $request, $data, bool $is3D = true)
    {
        parent::__construct($request, $data);

        $this->is3D = $is3D;

        if (!$is3D && is_string($data)) {
            $this->parsedResponse = Helper::xmlStringToObject($data);
        }
    }

    public function isSuccessful(): bool
    {
        if ($this->is3D) {
            return false;
        }

        return isset($this->parsedResponse->ResponseCode)
            && $this->parsedResponse->ResponseCode === '00';
    }

    public function isRedirect(): bool
    {
        return $this->is3D;
    }

    public function getRedirectUrl()
    {
        if (!$this->is3D) {
            return null;
        }

        /** @var PurchaseRequest $request */
        $request = $this->getRequest();

        return $request->getEndpoint();
    }

    public function getRedirectMethod(): string
    {
        return 'POST';
    }

    public function getRedirectData(): array
    {
        if (!$this->is3D) {
            return [];
        }

        return (array) $this->getData();
    }

    public function getMessage(): ?string
    {
        if ($this->is3D) {
            return null;
        }

        return $this->parsedResponse->ResponseMessage ?? null;
    }

    public function getCode(): ?string
    {
        if ($this->is3D) {
            return null;
        }

        return $this->parsedResponse->ResponseCode ?? null;
    }

    public function getParsedResponse(): ?object
    {
        return $this->parsedResponse;
    }

    public function getRedirectResponse()
    {
        $response = parent::getRedirectResponse();

        $response->setContent(str_replace('<body', "<body style='color:#FFF'", $response->getContent()));

        $script = '<script>
			document.forms[0].style.display = "none";
	        document.getElementsByTagName("section")[0].style.display = "block";

			setTimeout(function() {
			  document.body.style.color = "auto";
			  document.forms[0].style.display = "block";
			  document.getElementsByTagName("section")[0].style.display = "none";
			}, 5000);
		</script>';

        $response->setContent(str_replace('</body>', $this->redirectSpinner() . '</body>', $response->getContent()));

        $response->setContent(str_replace('</body>', "$script</body>", $response->getContent()));

        return $response;
    }

    protected function redirectSpinner(): string
    {
        $css = '<style>
					section {
					  width: 174px;
					  margin: 0 auto;
					  padding: 20px;
					}

					.spinner {
					  animation: rotate 1.4s linear infinite;
					  -webkit-animation: rotate 1.4s linear infinite;
					  -moz-animation: rotate 1.4s linear infinite;
					  width:114px;
					  height:114px;
					  position: relative;
					}

					.spinner-dot {
					  width:214px;
					  height:214px;
					  position: relative;
					  top: 0;
					}


					@keyframes rotate {
					  to {
					    transform: rotate(360deg);
					  }
					}

					@-webkit-keyframes rotate {
					  to {
					    -webkit-transform: rotate(360deg);
					  }
					}

					@-moz-keyframes rotate {
					  to {
					    transform: rotate(360deg);
					  }
					}

					.path {
					  stroke-dasharray: 170;
					  stroke-dashoffset: 20;
					}
			</style>';

        $html = '<section>
		  <svg class="spinner" width="174px" height="174px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
		     <circle class="path" fill="transparent" stroke-width="2" cx="33" cy="33" r="30" stroke="url(#gradient)"/>
		       <linearGradient id="gradient">
		         <stop offset="50%" stop-color="#42d179" stop-opacity="1"/>
		         <stop offset="65%" stop-color="#42d179" stop-opacity=".5"/>
		         <stop offset="100%" stop-color="#42d179" stop-opacity="0"/>
		       </linearGradient>
		    </circle>
		     <svg class="spinner-dot dot" width="5px" height="5px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg" x="37" y="1.5">
		       <circle class="path" fill="#42d179" cx="33" cy="33" r="30"/>
		      </circle>
		    </svg>
		  </svg>
		</section>';

        return $css . $html;
    }
}
