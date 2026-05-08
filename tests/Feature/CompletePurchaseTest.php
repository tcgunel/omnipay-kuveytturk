<?php

namespace Omnipay\KuveytTurk\Tests\Feature;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\KuveytTurk\Helpers\Helper;
use Omnipay\KuveytTurk\Message\CompletePurchaseRequest;
use Omnipay\KuveytTurk\Message\CompletePurchaseResponse;
use Omnipay\KuveytTurk\Tests\TestCase;

class CompletePurchaseTest extends TestCase
{
    /**
     * @throws InvalidRequestException
     * @throws \JsonException
     */
    public function test_complete_purchase_request_builds_provision_json()
    {
        $options = json_decode(
            file_get_contents(__DIR__ . '/../Mock/CompletePurchaseRequest.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $request = new CompletePurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize($options);

        $data = $request->getData();

        $this->assertIsArray($data);
        $this->assertSame(1, $data['language']);
        $this->assertSame('496', $data['merchantId']);
        $this->assertSame('400235', $data['customerId']);
        $this->assertSame('apiuser', $data['username']);
        $this->assertSame('1234', $data['amount']);
        $this->assertSame('22654838', $data['orderId']);
        $this->assertSame('KT-ORDER-001', $data['merchantOrderId']);
        $this->assertSame('67YtBfBRTZ0XBKnAHi8c/A==', $data['md']);
        $this->assertSame('https://example.com/success', $data['successUrl']);
        $this->assertSame('https://example.com/failure', $data['failUrl']);

        $expectedHash = Helper::hashNon3D('496', 'KT-ORDER-001', '1234', 'apiuser', 'Api123');
        $this->assertSame($expectedHash, $data['hashData']);
    }

    public function test_complete_purchase_sends_provision_request_and_parses_success()
    {
        $options = json_decode(
            file_get_contents(__DIR__ . '/../Mock/CompletePurchaseRequest.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->setMockHttpResponse('CompletePurchaseProvisionSuccess.txt');

        /** @var CompletePurchaseResponse $response */
        $response = $this->gateway->completePurchase($options)->send();

        $this->assertInstanceOf(CompletePurchaseResponse::class, $response);
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('00', $response->getCode());
        $this->assertSame('Onaylandi', $response->getMessage());
        $this->assertSame('KT-ORDER-001', $response->getTransactionReference());

        $requests = $this->getMockedRequests();
        $this->assertCount(1, $requests);

        $httpRequest = $requests[0];
        $this->assertSame('POST', $httpRequest->getMethod());
        $this->assertStringContainsString(
            'boatest.kuveytturk.com.tr/boa.virtualpos.services/KTPay/Provision',
            (string) $httpRequest->getUri(),
        );
        $this->assertStringContainsString('application/json', $httpRequest->getHeaderLine('Content-Type'));

        $body = json_decode((string) $httpRequest->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('496', $body['merchantId']);
        $this->assertSame('67YtBfBRTZ0XBKnAHi8c/A==', $body['md']);
        $this->assertSame('22654838', $body['orderId']);
    }

    public function test_complete_purchase_parses_error_response()
    {
        $options = json_decode(
            file_get_contents(__DIR__ . '/../Mock/CompletePurchaseRequest.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->setMockHttpResponse('CompletePurchaseProvisionError.txt');

        $response = $this->gateway->completePurchase($options)->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('EmptyMDException', $response->getCode());
        $this->assertSame('MD degeri bos', $response->getMessage());
    }

    public function test_complete_purchase_uses_prod_endpoint_when_test_mode_false()
    {
        $options = json_decode(
            file_get_contents(__DIR__ . '/../Mock/CompletePurchaseRequest.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $options['testMode'] = false;

        $this->setMockHttpResponse('CompletePurchaseProvisionSuccess.txt');

        $this->gateway->completePurchase($options)->send();

        $requests = $this->getMockedRequests();
        $httpRequest = $requests[0];

        $this->assertStringContainsString(
            'sanalpos.kuveytturk.com.tr/ServiceGateWay/KTPay/Provision',
            (string) $httpRequest->getUri(),
        );
    }

    public function test_complete_purchase_gateway_method_returns_correct_request()
    {
        $request = $this->gateway->completePurchase([
            'merchantId' => '496',
            'customerId' => '400235',
            'userName' => 'apiuser',
            'password' => 'Api123',
        ]);

        $this->assertInstanceOf(CompletePurchaseRequest::class, $request);
    }

    public function test_complete_purchase_request_validation_error()
    {
        $request = new CompletePurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize([
            'merchantId' => '',
            'customerId' => '',
            'userName' => '',
            'password' => '',
        ]);

        $this->expectException(InvalidRequestException::class);

        $request->getData();
    }
}
