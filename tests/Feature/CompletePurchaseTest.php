<?php

namespace Omnipay\KuveytTurk\Tests\Feature;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\KuveytTurk\Message\CompletePurchaseRequest;
use Omnipay\KuveytTurk\Message\CompletePurchaseResponse;
use Omnipay\KuveytTurk\Tests\TestCase;

class CompletePurchaseTest extends TestCase
{
	/**
	 * @throws InvalidRequestException
	 * @throws \JsonException
	 */
	public function test_complete_purchase_request_with_successful_auth()
	{
		$options = file_get_contents(__DIR__ . "/../Mock/CompletePurchaseRequest.json");

		$options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

		$authXml = file_get_contents(__DIR__ . "/../Mock/CompletePurchaseAuthResponseSuccess.xml");

		$options['authenticationResponse'] = base64_encode($authXml);
		$options['md'] = '67YtBfBRTZ0XBKnAHi8c/A==';

		$request = new CompletePurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

		$request->initialize($options);

		$data = $request->getData();

		$this->assertIsArray($data);

		// Auth should be successful
		$this->assertTrue($data['_authSuccess']);

		// Verify provision request data
		$this->assertEquals('TDV2.0.0', $data['APIVersion']);
		$this->assertEquals('496', $data['MerchantId']);
		$this->assertEquals('400235', $data['CustomerId']);
		$this->assertEquals('apiuser', $data['UserName']);
		$this->assertEquals('Sale', $data['TransactionType']);
		$this->assertEquals('0', $data['InstallmentCount']);
		$this->assertEquals('1234', $data['Amount']);
		$this->assertEquals('KT-ORDER-001', $data['MerchantOrderId']);
		$this->assertEquals('3', $data['TransactionSecurity']);

		// Verify MD data
		$this->assertEquals('67YtBfBRTZ0XBKnAHi8c/A==', $data['KuveytTurkVPosAdditionalData']['AdditionalData']['Data']);
		$this->assertEquals('MD', $data['KuveytTurkVPosAdditionalData']['AdditionalData']['Key']);

		// Verify hash is present
		$this->assertNotEmpty($data['HashData']);
	}

	/**
	 * @throws InvalidRequestException
	 * @throws \JsonException
	 */
	public function test_complete_purchase_request_with_failed_auth()
	{
		$options = file_get_contents(__DIR__ . "/../Mock/CompletePurchaseRequest.json");

		$options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

		$authXml = file_get_contents(__DIR__ . "/../Mock/CompletePurchaseAuthResponseFail.xml");

		$options['authenticationResponse'] = base64_encode($authXml);

		$request = new CompletePurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

		$request->initialize($options);

		$data = $request->getData();

		$this->assertIsArray($data);

		// Auth should be failed
		$this->assertFalse($data['_authSuccess']);

		$this->assertEquals('HashDataError', $data['ResponseCode']);
		$this->assertEquals('Guvenlik Hatasi - Loss Cevap', $data['ResponseMessage']);
	}

	public function test_complete_purchase_response_provision_success()
	{
		$httpResponse = $this->getMockHttpResponse('CompletePurchaseProvisionSuccess.txt');

		$response = new CompletePurchaseResponse(
			$this->getMockRequest(),
			$httpResponse->getBody()->getContents(),
			null
		);

		$this->assertTrue($response->isSuccessful());

		$this->assertEquals('00', $response->getCode());

		$this->assertEquals('Onaylandi', $response->getMessage());

		$this->assertEquals('KT-ORDER-001', $response->getTransactionReference());
	}

	public function test_complete_purchase_response_provision_error()
	{
		$httpResponse = $this->getMockHttpResponse('CompletePurchaseProvisionError.txt');

		$response = new CompletePurchaseResponse(
			$this->getMockRequest(),
			$httpResponse->getBody()->getContents(),
			null
		);

		$this->assertFalse($response->isSuccessful());

		$this->assertEquals('EmptyMDException', $response->getCode());

		$this->assertEquals('MD degeri bos', $response->getMessage());
	}

	public function test_complete_purchase_response_auth_failed()
	{
		$failedData = [
			'_authSuccess'   => false,
			'ResponseCode'   => 'HashDataError',
			'ResponseMessage' => 'Guvenlik Hatasi - Loss Cevap',
		];

		$response = new CompletePurchaseResponse(
			$this->getMockRequest(),
			$failedData,
			null
		);

		$this->assertFalse($response->isSuccessful());

		$this->assertEquals('HashDataError', $response->getCode());

		$this->assertEquals('Guvenlik Hatasi - Loss Cevap', $response->getMessage());
	}

	public function test_complete_purchase_sends_provision_request_on_success()
	{
		$options = file_get_contents(__DIR__ . "/../Mock/CompletePurchaseRequest.json");

		$options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

		$authXml = file_get_contents(__DIR__ . "/../Mock/CompletePurchaseAuthResponseSuccess.xml");

		$options['authenticationResponse'] = base64_encode($authXml);
		$options['md'] = '67YtBfBRTZ0XBKnAHi8c/A==';

		$this->setMockHttpResponse('CompletePurchaseProvisionSuccess.txt');

		$response = $this->gateway->completePurchase($options)->send();

		$this->assertTrue($response->isSuccessful());

		// Verify the HTTP request was sent to provision endpoint
		$requests = $this->getMockedRequests();
		$this->assertCount(1, $requests);

		$httpRequest = $requests[0];
		$this->assertEquals('POST', $httpRequest->getMethod());
		$this->assertStringContainsString(
			'boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelProvisionGate',
			(string) $httpRequest->getUri()
		);

		// Verify the body is XML
		$body = (string) $httpRequest->getBody();
		$this->assertStringContainsString('KuveytTurkVPosMessage', $body);
		$this->assertStringContainsString('<MerchantId>496</MerchantId>', $body);
		$this->assertStringContainsString('67YtBfBRTZ0XBKnAHi8c/A==', $body);
	}

	public function test_complete_purchase_does_not_send_provision_on_auth_failure()
	{
		$options = file_get_contents(__DIR__ . "/../Mock/CompletePurchaseRequest.json");

		$options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

		$authXml = file_get_contents(__DIR__ . "/../Mock/CompletePurchaseAuthResponseFail.xml");

		$options['authenticationResponse'] = base64_encode($authXml);

		$response = $this->gateway->completePurchase($options)->send();

		$this->assertFalse($response->isSuccessful());

		// No HTTP request should be sent
		$requests = $this->getMockedRequests();
		$this->assertCount(0, $requests);
	}

	public function test_complete_purchase_prod_endpoint()
	{
		$options = file_get_contents(__DIR__ . "/../Mock/CompletePurchaseRequest.json");

		$options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

		$authXml = file_get_contents(__DIR__ . "/../Mock/CompletePurchaseAuthResponseSuccess.xml");

		$options['authenticationResponse'] = base64_encode($authXml);
		$options['md'] = '67YtBfBRTZ0XBKnAHi8c/A==';
		$options['testMode'] = false;

		$this->setMockHttpResponse('CompletePurchaseProvisionSuccess.txt');

		$this->gateway->completePurchase($options)->send();

		$requests = $this->getMockedRequests();
		$httpRequest = $requests[0];

		$this->assertStringContainsString(
			'sanalpos.kuveytturk.com.tr/ServiceGateWay/Home/ThreeDModelProvisionGate',
			(string) $httpRequest->getUri()
		);
	}

	public function test_complete_purchase_gateway_method()
	{
		$request = $this->gateway->completePurchase([
			'merchantId' => '496',
			'customerId' => '400235',
			'userName'   => 'apiuser',
			'password'   => 'Api123',
		]);

		$this->assertInstanceOf(CompletePurchaseRequest::class, $request);
	}

	public function test_complete_purchase_request_validation_error()
	{
		$request = new CompletePurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

		$request->initialize([
			'merchantId' => '',
			'customerId' => '',
			'userName'   => '',
			'password'   => '',
		]);

		$this->expectException(InvalidRequestException::class);

		$request->getData();
	}
}
