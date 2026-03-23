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
	public function test_3d_purchase_request()
	{
		$options = file_get_contents(__DIR__ . "/../Mock/PurchaseRequest3D.json");

		$options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

		$request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

		$request->initialize($options);

		$data = $request->getData();

		$this->assertIsArray($data);

		// Verify API version
		$this->assertEquals('TDV2.0.0', $data['APIVersion']);

		// Verify card info
		$this->assertEquals('4111111111111111', $data['CardNumber']);
		$this->assertEquals('99', $data['CardExpireDateYear']);
		$this->assertEquals('12', $data['CardExpireDateMonth']);
		$this->assertEquals('000', $data['CardCVV2']);
		$this->assertEquals('Example User', $data['CardHolderName']);

		// Verify transaction info
		$this->assertEquals('0', $data['BatchID']);
		$this->assertEquals('Sale', $data['TransactionType']);
		$this->assertEquals('0', $data['InstallmentCount']);
		$this->assertEquals('1234', $data['Amount']);
		$this->assertEquals('1234', $data['DisplayAmount']);
		$this->assertEquals('0949', $data['CurrencyCode']);
		$this->assertEquals('KT-ORDER-001', $data['MerchantOrderId']);
		$this->assertEquals('3', $data['TransactionSecurity']);

		// Verify 3D URLs
		$this->assertEquals('https://example.com/success', $data['OkUrl']);
		$this->assertEquals('https://example.com/failure', $data['FailUrl']);

		// Verify merchant info
		$this->assertEquals('apiuser', $data['UserName']);
		$this->assertEquals('496', $data['MerchantId']);
		$this->assertEquals('400235', $data['CustomerId']);

		// Verify hash is present and is a base64 string
		$this->assertNotEmpty($data['HashData']);

		// Verify hash calculation: SHA1Base64(MerchantId + MerchantOrderId + Amount + OkUrl + FailUrl + UserName + SHA1Base64(Password))
		$expectedHash = Helper::hash3D(
			'496',
			'KT-ORDER-001',
			'1234',
			'https://example.com/success',
			'https://example.com/failure',
			'apiuser',
			'Api123',
		);
		$this->assertEquals($expectedHash, $data['HashData']);
	}

	/**
	 * @throws InvalidRequestException
	 * @throws InvalidCreditCardException
	 * @throws \JsonException
	 */
	public function test_non3d_purchase_request()
	{
		$options = file_get_contents(__DIR__ . "/../Mock/PurchaseRequestNon3D.json");

		$options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

		$request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

		$request->initialize($options);

		$data = $request->getData();

		$this->assertIsArray($data);

		// Verify transaction security is 1 for non-3D
		$this->assertEquals('1', $data['TransactionSecurity']);

		// Verify no OkUrl/FailUrl for non-3D
		$this->assertArrayNotHasKey('OkUrl', $data);
		$this->assertArrayNotHasKey('FailUrl', $data);

		// Verify hash is non-3D: SHA1Base64(MerchantId + MerchantOrderId + Amount + UserName + SHA1Base64(Password))
		$expectedHash = Helper::hashNon3D(
			'496',
			'KT-ORDER-002',
			'1234',
			'apiuser',
			'Api123',
		);
		$this->assertEquals($expectedHash, $data['HashData']);
	}

	public function test_purchase_request_validation_error()
	{
		$options = file_get_contents(__DIR__ . "/../Mock/PurchaseRequest-ValidationError.json");

		$options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

		$request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

		$request->initialize($options);

		$this->expectException(InvalidRequestException::class);

		$request->getData();
	}

	/**
	 * @throws InvalidRequestException
	 * @throws InvalidCreditCardException
	 * @throws \JsonException
	 */
	public function test_3d_purchase_response_is_redirect()
	{
		$options = file_get_contents(__DIR__ . "/../Mock/PurchaseRequest3D.json");

		$options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

		$request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

		/** @var PurchaseResponse $response */
		$response = $request->initialize($options)->send();

		$this->assertFalse($response->isSuccessful());

		$this->assertTrue($response->isRedirect());

		$this->assertEquals('POST', $response->getRedirectMethod());

		$this->assertEquals(
			'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelPayGate',
			$response->getRedirectUrl()
		);

		$redirectData = $response->getRedirectData();

		$this->assertIsArray($redirectData);

		$this->assertEquals($request->getData(), $redirectData);
	}

	/**
	 * @throws InvalidRequestException
	 * @throws InvalidCreditCardException
	 * @throws \JsonException
	 */
	public function test_3d_purchase_response_prod_endpoint()
	{
		$options = file_get_contents(__DIR__ . "/../Mock/PurchaseRequest3D.json");

		$options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

		$options['testMode'] = false;

		$request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

		/** @var PurchaseResponse $response */
		$response = $request->initialize($options)->send();

		$this->assertEquals(
			'https://sanalpos.kuveytturk.com.tr/ServiceGateWay/Home/ThreeDModelPayGate',
			$response->getRedirectUrl()
		);
	}

	public function test_non3d_purchase_sends_http_request_success()
	{
		$options = file_get_contents(__DIR__ . "/../Mock/PurchaseRequestNon3D.json");

		$options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

		$this->setMockHttpResponse('PurchaseResponseNon3DSuccess.txt');

		$response = $this->gateway->purchase($options)->send();

		$this->assertTrue($response->isSuccessful());

		$this->assertFalse($response->isRedirect());

		$this->assertEquals('00', $response->getCode());

		$this->assertEquals('Onaylandi', $response->getMessage());

		// Verify the HTTP request was sent
		$requests = $this->getMockedRequests();
		$this->assertCount(1, $requests);

		$httpRequest = $requests[0];
		$this->assertEquals('POST', $httpRequest->getMethod());
		$this->assertStringContainsString(
			'boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/Non3DPayGate',
			(string) $httpRequest->getUri()
		);

		// Verify the body is XML
		$body = (string) $httpRequest->getBody();
		$this->assertStringContainsString('KuveytTurkVPosMessage', $body);
		$this->assertStringContainsString('<MerchantId>496</MerchantId>', $body);
		$this->assertStringContainsString('<MerchantOrderId>KT-ORDER-002</MerchantOrderId>', $body);
		$this->assertStringContainsString('<TransactionSecurity>1</TransactionSecurity>', $body);
	}

	public function test_non3d_purchase_sends_http_request_error()
	{
		$options = file_get_contents(__DIR__ . "/../Mock/PurchaseRequestNon3D.json");

		$options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

		$this->setMockHttpResponse('PurchaseResponseNon3DError.txt');

		$response = $this->gateway->purchase($options)->send();

		$this->assertFalse($response->isSuccessful());

		$this->assertFalse($response->isRedirect());

		$this->assertEquals('51', $response->getCode());

		$this->assertEquals('Yetersiz bakiye', $response->getMessage());
	}

	public function test_non3d_purchase_prod_endpoint()
	{
		$options = file_get_contents(__DIR__ . "/../Mock/PurchaseRequestNon3D.json");

		$options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

		$options['testMode'] = false;

		$this->setMockHttpResponse('PurchaseResponseNon3DSuccess.txt');

		$this->gateway->purchase($options)->send();

		$requests = $this->getMockedRequests();
		$httpRequest = $requests[0];

		$this->assertStringContainsString(
			'sanalpos.kuveytturk.com.tr/ServiceGateWay/Home/Non3DPayGate',
			(string) $httpRequest->getUri()
		);
	}

	public function test_purchase_gateway_method()
	{
		$request = $this->gateway->purchase([
			'merchantId' => '496',
			'customerId' => '400235',
			'userName'   => 'apiuser',
			'password'   => 'Api123',
		]);

		$this->assertInstanceOf(PurchaseRequest::class, $request);
	}

	public function test_currency_codes()
	{
		$this->assertEquals('0949', CurrencyCode::fromAlpha('TRY'));
		$this->assertEquals('0840', CurrencyCode::fromAlpha('USD'));
		$this->assertEquals('0978', CurrencyCode::fromAlpha('EUR'));
		$this->assertEquals('0826', CurrencyCode::fromAlpha('GBP'));

		// Unknown currency defaults to TRY
		$this->assertEquals('0949', CurrencyCode::fromAlpha('UNKNOWN'));
	}

	/**
	 * @throws InvalidRequestException
	 * @throws InvalidCreditCardException
	 * @throws \JsonException
	 */
	public function test_purchase_with_installment()
	{
		$options = file_get_contents(__DIR__ . "/../Mock/PurchaseRequest3D.json");

		$options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

		$options['installment'] = 3;

		$request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

		$request->initialize($options);

		$data = $request->getData();

		$this->assertEquals('3', $data['InstallmentCount']);
	}

	/**
	 * @throws InvalidRequestException
	 * @throws InvalidCreditCardException
	 * @throws \JsonException
	 */
	public function test_purchase_with_usd_currency()
	{
		$options = file_get_contents(__DIR__ . "/../Mock/PurchaseRequest3D.json");

		$options = json_decode($options, true, 512, JSON_THROW_ON_ERROR);

		$options['currency'] = 'USD';

		$request = new PurchaseRequest($this->getHttpClient(), $this->getHttpRequest());

		$request->initialize($options);

		$data = $request->getData();

		$this->assertEquals('0840', $data['CurrencyCode']);
	}
}
