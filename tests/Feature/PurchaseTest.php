<?php

namespace Omnipay\KuveytTurk\Tests\Feature;

use Omnipay\Common\Exception\InvalidCreditCardException;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\KuveytTurk\Constants\CurrencyCode;
use Omnipay\KuveytTurk\Helpers\Helper;
use Omnipay\KuveytTurk\Message\PurchaseRequest;
use Omnipay\KuveytTurk\Message\PurchaseResponse;
use Omnipay\KuveytTurk\Tests\TestCase;

class PurchaseTest extends TestCase
{
    /**
     * @throws InvalidRequestException
     * @throws InvalidCreditCardException
     * @throws \JsonException
     */
    public function test_3d_purchase_request_builds_json_body()
    {
        $options = json_decode(
            file_get_contents(__DIR__ . '/../Mock/PurchaseRequest3D.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize($options);

        $data = $request->getData();

        $this->assertIsArray($data);

        $this->assertSame(1, $data['paymentType']);
        $this->assertSame(1, $data['language']);
        $this->assertSame('KT-ORDER-001', $data['merchantOrderId']);
        $this->assertSame('https://example.com/success', $data['successUrl']);
        $this->assertSame('https://example.com/failure', $data['failUrl']);
        $this->assertSame(496, $data['merchantId']);
        $this->assertSame(400235, $data['customerId']);
        $this->assertSame('apiuser', $data['username']);
        $this->assertSame('1234', $data['amount']);
        $this->assertSame('0949', $data['currency']);
        $this->assertSame(0, $data['installmentCount']);

        $this->assertSame('4111111111111111', $data['card']['cardNumber']);
        $this->assertSame('Example User', $data['card']['cardHolderName']);
        $this->assertSame('12', $data['card']['expireMonth']);
        $this->assertSame('99', $data['card']['expireYear']);
        $this->assertSame('000', $data['card']['securityCode']);

        $this->assertArrayHasKey('customer', $data);
        $this->assertArrayHasKey('ipAddress', $data['customer']);
        $this->assertSame($data['customer']['ipAddress'], $data['cardHolderIp']);

        $expectedHash = Helper::hash3D(
            '496',
            'KT-ORDER-001',
            '1234',
            'https://example.com/success',
            'https://example.com/failure',
            'apiuser',
            'Api123',
        );

        $this->assertSame($expectedHash, $data['hashData']);
    }

    /**
     * @throws InvalidRequestException
     * @throws InvalidCreditCardException
     * @throws \JsonException
     */
    public function test_non3d_purchase_request_builds_xml_body()
    {
        $options = json_decode(
            file_get_contents(__DIR__ . '/../Mock/PurchaseRequestNon3D.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize($options);

        $data = $request->getData();

        $this->assertIsArray($data);
        $this->assertSame('1', $data['TransactionSecurity']);
        $this->assertArrayNotHasKey('OkUrl', $data);
        $this->assertArrayNotHasKey('FailUrl', $data);
        $this->assertSame('0949', $data['CurrencyCode']);

        $expectedHash = Helper::hashNon3D('496', 'KT-ORDER-002', '1234', 'apiuser', 'Api123');

        $this->assertSame($expectedHash, $data['HashData']);
    }

    public function test_purchase_request_validation_error()
    {
        $options = json_decode(
            file_get_contents(__DIR__ . '/../Mock/PurchaseRequest-ValidationError.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize($options);

        $this->expectException(InvalidRequestException::class);

        $request->getData();
    }

    public function test_3d_purchase_sends_json_to_test_endpoint_and_returns_html_redirect()
    {
        $options = json_decode(
            file_get_contents(__DIR__ . '/../Mock/PurchaseRequest3D.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->setMockHttpResponse('PurchaseResponse3DHtml.txt');

        /** @var PurchaseResponse $response */
        $response = $this->gateway->purchase($options)->send();

        $this->assertInstanceOf(PurchaseResponse::class, $response);
        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isRedirect());
        $this->assertNotNull($response->getRawHtml());
        $this->assertStringContainsString('document.forms[0].submit()', $response->getRedirectResponse()->getContent());

        $requests = $this->getMockedRequests();
        $this->assertCount(1, $requests);

        $httpRequest = $requests[0];
        $this->assertSame('POST', $httpRequest->getMethod());
        $this->assertStringContainsString(
            'boatest.kuveytturk.com.tr/boa.virtualpos.services/KTPay/Payment',
            (string) $httpRequest->getUri(),
        );
        $this->assertStringContainsString('application/json', $httpRequest->getHeaderLine('Content-Type'));

        $body = json_decode((string) $httpRequest->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(496, $body['merchantId']);
        $this->assertSame('KT-ORDER-001', $body['merchantOrderId']);
    }

    public function test_3d_redirect_response_injects_target_top_into_forms_to_escape_iframes()
    {
        $options = json_decode(
            file_get_contents(__DIR__ . '/../Mock/PurchaseRequest3D.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->setMockHttpResponse('PurchaseResponse3DHtml.txt');

        /** @var PurchaseResponse $response */
        $response = $this->gateway->purchase($options)->send();

        $content = $response->getRedirectResponse()->getContent();

        $this->assertStringContainsString('<form target="_top"', $content);
        $this->assertStringNotContainsString('<form action=', $content);
    }

    public function test_3d_redirect_response_leaves_existing_target_alone()
    {
        $request = new \Omnipay\KuveytTurk\Message\PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

        $crafted = '<html><body><form target="_self" action="/x"></form></body></html>';
        $purchaseResponse = new PurchaseResponse($request, $crafted, true);

        $content = $purchaseResponse->getRedirectResponse()->getContent();

        $this->assertStringContainsString('target="_self"', $content);
        $this->assertStringNotContainsString('target="_top"', $content);
    }

    public function test_3d_purchase_handles_json_error_response()
    {
        $options = json_decode(
            file_get_contents(__DIR__ . '/../Mock/PurchaseRequest3D.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->setMockHttpResponse('PurchaseResponse3DError.txt');

        /** @var PurchaseResponse $response */
        $response = $this->gateway->purchase($options)->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isRedirect());
        $this->assertSame('HashError', $response->getCode());
        $this->assertSame('Guvenlik dogrulamasi basarisiz', $response->getMessage());
    }

    public function test_3d_purchase_uses_prod_endpoint_when_test_mode_is_false()
    {
        $options = json_decode(
            file_get_contents(__DIR__ . '/../Mock/PurchaseRequest3D.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $options['testMode'] = false;

        $this->setMockHttpResponse('PurchaseResponse3DHtml.txt');

        $this->gateway->purchase($options)->send();

        $requests = $this->getMockedRequests();
        $httpRequest = $requests[0];

        $this->assertStringContainsString(
            'sanalpos.kuveytturk.com.tr/ServiceGateWay/KTPay/Payment',
            (string) $httpRequest->getUri(),
        );
    }

    public function test_non3d_purchase_sends_xml_and_parses_success()
    {
        $options = json_decode(
            file_get_contents(__DIR__ . '/../Mock/PurchaseRequestNon3D.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->setMockHttpResponse('PurchaseResponseNon3DSuccess.txt');

        $response = $this->gateway->purchase($options)->send();

        $this->assertTrue($response->isSuccessful());
        $this->assertFalse($response->isRedirect());
        $this->assertSame('00', $response->getCode());
        $this->assertSame('Onaylandi', $response->getMessage());

        $requests = $this->getMockedRequests();
        $this->assertCount(1, $requests);

        $httpRequest = $requests[0];
        $this->assertSame('POST', $httpRequest->getMethod());
        $this->assertStringContainsString(
            'boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/Non3DPayGate',
            (string) $httpRequest->getUri(),
        );

        $body = (string) $httpRequest->getBody();
        $this->assertStringContainsString('KuveytTurkVPosMessage', $body);
        $this->assertStringContainsString('<MerchantId>496</MerchantId>', $body);
        $this->assertStringContainsString('<MerchantOrderId>KT-ORDER-002</MerchantOrderId>', $body);
        $this->assertStringContainsString('<TransactionSecurity>1</TransactionSecurity>', $body);
    }

    public function test_non3d_purchase_parses_error_response()
    {
        $options = json_decode(
            file_get_contents(__DIR__ . '/../Mock/PurchaseRequestNon3D.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->setMockHttpResponse('PurchaseResponseNon3DError.txt');

        $response = $this->gateway->purchase($options)->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isRedirect());
        $this->assertSame('51', $response->getCode());
        $this->assertSame('Yetersiz bakiye', $response->getMessage());
    }

    public function test_non3d_purchase_uses_prod_endpoint()
    {
        $options = json_decode(
            file_get_contents(__DIR__ . '/../Mock/PurchaseRequestNon3D.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $options['testMode'] = false;

        $this->setMockHttpResponse('PurchaseResponseNon3DSuccess.txt');

        $this->gateway->purchase($options)->send();

        $requests = $this->getMockedRequests();
        $httpRequest = $requests[0];

        $this->assertStringContainsString(
            'sanalpos.kuveytturk.com.tr/ServiceGateWay/Home/Non3DPayGate',
            (string) $httpRequest->getUri(),
        );
    }

    public function test_purchase_gateway_method_returns_purchase_request_instance()
    {
        $request = $this->gateway->purchase([
            'merchantId' => '496',
            'customerId' => '400235',
            'userName' => 'apiuser',
            'password' => 'Api123',
        ]);

        $this->assertInstanceOf(PurchaseRequest::class, $request);
    }

    public function test_currency_codes_are_four_digit_iso_4217_padded()
    {
        $this->assertSame('0949', CurrencyCode::fromAlpha('TRY'));
        $this->assertSame('0840', CurrencyCode::fromAlpha('USD'));
        $this->assertSame('0978', CurrencyCode::fromAlpha('EUR'));
        $this->assertSame('0826', CurrencyCode::fromAlpha('GBP'));
        $this->assertSame('0949', CurrencyCode::fromAlpha('UNKNOWN'));
    }

    /**
     * @throws InvalidRequestException
     * @throws InvalidCreditCardException
     * @throws \JsonException
     */
    public function test_purchase_with_installment_passes_through()
    {
        $options = json_decode(
            file_get_contents(__DIR__ . '/../Mock/PurchaseRequest3D.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $options['installment'] = 3;

        $request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize($options);

        $data = $request->getData();

        $this->assertSame(3, $data['installmentCount']);
    }

    /**
     * @throws InvalidRequestException
     * @throws InvalidCreditCardException
     * @throws \JsonException
     */
    public function test_purchase_with_usd_currency()
    {
        $options = json_decode(
            file_get_contents(__DIR__ . '/../Mock/PurchaseRequest3D.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $options['currency'] = 'USD';

        $request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize($options);

        $data = $request->getData();

        $this->assertSame('0840', $data['currency']);
    }
}
