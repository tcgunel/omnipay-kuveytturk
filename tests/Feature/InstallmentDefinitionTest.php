<?php

namespace Omnipay\KuveytTurk\Tests\Feature;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\KuveytTurk\Message\InstallmentDefinitionRequest;
use Omnipay\KuveytTurk\Message\InstallmentDefinitionResponse;
use Omnipay\KuveytTurk\Tests\TestCase;

class InstallmentDefinitionTest extends TestCase
{
    public function test_request_builds_json_body()
    {
        $request = new InstallmentDefinitionRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize([
            'merchantId' => '496',
            'testMode' => true,
        ]);

        $data = $request->getData();

        $this->assertSame(496, $data['merchantId']);
    }

    public function test_request_validation_error_when_merchant_id_missing()
    {
        $request = new InstallmentDefinitionRequest($this->getHttpClient(), $this->getHttpRequest());

        $request->initialize(['merchantId' => '']);

        $this->expectException(InvalidRequestException::class);

        $request->getData();
    }

    public function test_request_sends_to_test_endpoint_and_parses_success()
    {
        $this->setMockHttpResponse('InstallmentDefinitionSuccess.txt');

        /** @var InstallmentDefinitionResponse $response */
        $response = $this->gateway->installmentDefinition([
            'merchantId' => '496',
            'testMode' => true,
        ])->send();

        $this->assertInstanceOf(InstallmentDefinitionResponse::class, $response);
        $this->assertTrue($response->isSuccessful());

        $installments = $response->getInstallments();
        $this->assertCount(5, $installments);
        $this->assertSame(1, $installments[0]->installment);
        $this->assertSame(0.0, $installments[0]->rate);
        $this->assertSame(9, $installments[4]->installment);

        $requests = $this->getMockedRequests();
        $httpRequest = $requests[0];

        $this->assertSame('POST', $httpRequest->getMethod());
        $this->assertStringContainsString(
            'boatest.kuveytturk.com.tr/boa.virtualpos.services/KTPay/GetMerchantInstallmentDefinition',
            (string) $httpRequest->getUri(),
        );
        $this->assertStringContainsString('application/json', $httpRequest->getHeaderLine('Content-Type'));
    }

    public function test_request_uses_prod_endpoint_when_test_mode_false()
    {
        $this->setMockHttpResponse('InstallmentDefinitionSuccess.txt');

        $this->gateway->installmentDefinition([
            'merchantId' => '496',
            'testMode' => false,
        ])->send();

        $requests = $this->getMockedRequests();
        $httpRequest = $requests[0];

        $this->assertStringContainsString(
            'sanalpos.kuveytturk.com.tr/ServiceGateWay/KTPay/GetMerchantInstallmentDefinition',
            (string) $httpRequest->getUri(),
        );
    }
}
