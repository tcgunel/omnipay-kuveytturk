<?php

namespace Omnipay\KuveytTurk\Tests\Feature;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\KuveytTurk\Message\IsOnusCardRequest;
use Omnipay\KuveytTurk\Message\IsOnusCardResponse;
use Omnipay\KuveytTurk\Tests\TestCase;

class IsOnusCardTest extends TestCase
{
    public function test_request_builds_card_number_only_body()
    {
        $request = new IsOnusCardRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize([
            'cardNumber' => '4111111111111111',
            'testMode' => true,
        ]);

        $data = $request->getData();

        $this->assertSame('4111111111111111', $data['cardNumber']);
        $this->assertArrayNotHasKey('merchantId', $data);
    }

    public function test_request_accepts_card_bin_alias_for_card_number()
    {
        $request = new IsOnusCardRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize([
            'cardBin' => '411111',
            'testMode' => true,
        ]);

        $data = $request->getData();

        $this->assertSame('411111', $data['cardNumber']);
    }

    public function test_request_validation_error_when_card_number_missing()
    {
        $request = new IsOnusCardRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize(['testMode' => true]);

        $this->expectException(InvalidRequestException::class);

        $request->getData();
    }

    public function test_request_sends_to_test_endpoint_and_parses_onus_true()
    {
        $this->setMockHttpResponse('IsOnusCardSuccess.txt');

        /** @var IsOnusCardResponse $response */
        $response = $this->gateway->isOnusCard([
            'cardNumber' => '4111111111111111',
            'testMode' => true,
        ])->send();

        $this->assertInstanceOf(IsOnusCardResponse::class, $response);
        $this->assertTrue($response->isSuccessful());
        $this->assertTrue($response->isOnus());

        $requests = $this->getMockedRequests();
        $httpRequest = $requests[0];

        $this->assertSame('POST', $httpRequest->getMethod());
        $this->assertStringContainsString(
            'boatest.kuveytturk.com.tr/boa.virtualpos.services/KTPay/IsOnusCard',
            (string) $httpRequest->getUri(),
        );
        $this->assertStringContainsString('application/json', $httpRequest->getHeaderLine('Content-Type'));

        $body = json_decode((string) $httpRequest->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['cardNumber' => '4111111111111111'], $body);
    }

    public function test_request_parses_not_onus()
    {
        $this->setMockHttpResponse('IsOnusCardNotOnus.txt');

        /** @var IsOnusCardResponse $response */
        $response = $this->gateway->isOnusCard([
            'cardNumber' => '5555555555554444',
            'testMode' => true,
        ])->send();

        $this->assertTrue($response->isSuccessful());
        $this->assertFalse($response->isOnus());
    }

    public function test_request_uses_prod_endpoint_when_test_mode_false()
    {
        $this->setMockHttpResponse('IsOnusCardSuccess.txt');

        $this->gateway->isOnusCard([
            'cardNumber' => '4111111111111111',
            'testMode' => false,
        ])->send();

        $requests = $this->getMockedRequests();
        $httpRequest = $requests[0];

        $this->assertStringContainsString(
            'sanalpos.kuveytturk.com.tr/ServiceGateWay/KTPay/IsOnusCard',
            (string) $httpRequest->getUri(),
        );
    }
}
