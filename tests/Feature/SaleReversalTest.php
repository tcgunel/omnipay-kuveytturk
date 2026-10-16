<?php

namespace Omnipay\KuveytTurk\Tests\Feature;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\KuveytTurk\Constants\SaleReversalType;
use Omnipay\KuveytTurk\Message\CancelRequest;
use Omnipay\KuveytTurk\Message\RefundRequest;
use Omnipay\KuveytTurk\Message\SaleReversalResponse;
use Omnipay\KuveytTurk\Tests\TestCase;

class SaleReversalTest extends TestCase
{
    /**
     * @return array<string, string>
     */
    private function options(): array
    {
        return [
            'merchantId' => '496',
            'customerId' => '400235',
            'userName' => 'apiuser',
            'password' => 'Api123',
            'orderId' => '22654838',
            'transactionId' => 'KT-ORDER-001',
        ];
    }

    public function test_cancel_builds_sale_reversal_json_with_independently_pinned_hash()
    {
        $request = new CancelRequest($this->getHttpClient(), $this->getHttpRequest());
        $request->initialize($this->options());

        $data = $request->getData();

        $this->assertSame(1, $data['language']);
        $this->assertSame(SaleReversalType::CANCEL, $data['saleReversalType']);
        $this->assertSame('496', $data['merchantId']);
        $this->assertSame('400235', $data['customerId']);
        $this->assertSame('apiuser', $data['username']);
        $this->assertSame(22654838, $data['orderId']);
        $this->assertSame('KT-ORDER-001', $data['merchantOrderId']);
        $this->assertSame('0', $data['amount']);

        // python3: base64(hmac_sha512("496" + "KT-ORDER-001" + "0" + "apiuser"
        // + base64(sha1("Api123")), base64(sha1("Api123"))))
        $this->assertSame(
            'Em9ZrQpdBxD/vXSrhJvoikcyEdkCKYqSv6qxrFcet16V1U6gZoiMkUu6s5drZqYjyg6SOyUI0ZHKNogV52ZqIg==',
            $data['hashData'],
        );
    }

    public function test_full_refund_builds_drawback_with_zero_amount()
    {
        $request = new RefundRequest($this->getHttpClient(), $this->getHttpRequest());
        $request->initialize($this->options());

        $data = $request->getData();

        $this->assertSame(SaleReversalType::DRAWBACK, $data['saleReversalType']);
        $this->assertSame('0', $data['amount']);

        // Full refunds hash exactly like cancellations (amount "0").
        $this->assertSame(
            'Em9ZrQpdBxD/vXSrhJvoikcyEdkCKYqSv6qxrFcet16V1U6gZoiMkUu6s5drZqYjyg6SOyUI0ZHKNogV52ZqIg==',
            $data['hashData'],
        );
    }

    public function test_partial_refund_builds_partial_drawback_with_kurus_amount()
    {
        $request = new RefundRequest($this->getHttpClient(), $this->getHttpRequest());
        $request->initialize($this->options() + ['amount' => '12.34']);

        $data = $request->getData();

        $this->assertSame(SaleReversalType::PARTIAL_DRAWBACK, $data['saleReversalType']);
        $this->assertSame('1234', $data['amount']);

        // python3: same formula with amount "1234".
        $this->assertSame(
            'Pd/7nr0c2JLiTUyauDJUeVvCJqv2C2t+uVeqQGc2+tuXpAaIDHw8m333ekk7WmsTQguFWtBVIhfeF2qEyKWu5w==',
            $data['hashData'],
        );
    }

    public function test_cancel_sends_sale_reversal_request_and_parses_success()
    {
        $this->setMockHttpResponse('SaleReversalSuccess.txt');

        /** @var SaleReversalResponse $response */
        $response = $this->gateway->cancel($this->options() + ['testMode' => true])->send();

        $this->assertInstanceOf(SaleReversalResponse::class, $response);
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('00', $response->getCode());
        $this->assertSame('OTORİZASYON VERİLDİ', $response->getMessage());
        $this->assertSame('203418187', $response->getTransactionReference());

        $requests = $this->getMockedRequests();
        $this->assertCount(1, $requests);

        $httpRequest = $requests[0];
        $this->assertSame('POST', $httpRequest->getMethod());
        $this->assertStringContainsString(
            'boatest.kuveytturk.com.tr/boa.virtualpos.services/KTPay/SaleReversal',
            (string) $httpRequest->getUri(),
        );
        $this->assertStringContainsString('application/json', $httpRequest->getHeaderLine('Content-Type'));

        $body = json_decode((string) $httpRequest->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(SaleReversalType::CANCEL, $body['saleReversalType']);
        $this->assertSame('0', $body['amount']);
        $this->assertSame(22654838, $body['orderId']);
        $this->assertSame('KT-ORDER-001', $body['merchantOrderId']);
    }

    public function test_refund_parses_error_response()
    {
        $this->setMockHttpResponse('SaleReversalError.txt');

        $response = $this->gateway->refund($this->options())->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('OrderNotFoundException', $response->getCode());
        $this->assertSame('İşlem bulunamadı.', $response->getMessage());
    }

    public function test_sale_reversal_uses_prod_endpoint_when_test_mode_false()
    {
        $this->setMockHttpResponse('SaleReversalSuccess.txt');

        $this->gateway->refund($this->options() + ['testMode' => false])->send();

        $requests = $this->getMockedRequests();

        $this->assertStringContainsString(
            'sanalpos.kuveytturk.com.tr/ServiceGateWay/KTPay/SaleReversal',
            (string) $requests[0]->getUri(),
        );
    }

    public function test_cancel_gateway_method_returns_correct_request()
    {
        $this->assertInstanceOf(CancelRequest::class, $this->gateway->cancel($this->options()));
    }

    public function test_refund_gateway_method_returns_correct_request()
    {
        $this->assertInstanceOf(RefundRequest::class, $this->gateway->refund($this->options()));
    }

    public function test_sale_reversal_request_validation_error()
    {
        $request = new CancelRequest($this->getHttpClient(), $this->getHttpRequest());
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
