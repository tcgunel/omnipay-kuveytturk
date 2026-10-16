<?php

namespace Omnipay\KuveytTurk\Tests\Feature;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\KuveytTurk\Message\FetchTransactionsRequest;
use Omnipay\KuveytTurk\Message\FetchTransactionsResponse;
use Omnipay\KuveytTurk\Tests\TestCase;

class FetchTransactionsTest extends TestCase
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
            'transactionId' => 'KT-ORDER-001',
        ];
    }

    public function test_fetch_transactions_builds_get_transactions_json_with_independently_pinned_hash()
    {
        $request = new FetchTransactionsRequest($this->getHttpClient(), $this->getHttpRequest());
        $request->initialize($this->options());

        $data = $request->getData();

        $this->assertSame(1, $data['language']);
        $this->assertSame('496', $data['merchantId']);
        $this->assertSame('400235', $data['customerId']);
        $this->assertSame('apiuser', $data['username']);
        $this->assertSame('KT-ORDER-001', $data['merchantOrderId']);
        $this->assertArrayNotHasKey('orderId', $data);

        // python3: base64(hmac_sha512("496" + "KT-ORDER-001" + "apiuser"
        // + base64(sha1("Api123")), base64(sha1("Api123"))))
        $this->assertSame(
            '65J37OOJ3R/Gq/ipxEdVtEDVEObSWD4EgsBLprhevFelgzxBccfQ/DEnpWlFFiEaCGuCaFMBkaXc8mlnlaDjug==',
            $data['hashData'],
        );
    }

    public function test_fetch_transactions_includes_optional_filters_when_given()
    {
        $request = new FetchTransactionsRequest($this->getHttpClient(), $this->getHttpRequest());
        $request->initialize($this->options() + [
            'orderId' => '203418187',
            'startDate' => '2024-06-01 00:00:00',
            'endDate' => '2024-06-30 23:59:59',
        ]);

        $data = $request->getData();

        $this->assertSame(203418187, $data['orderId']);
        $this->assertSame('2024-06-01 00:00:00', $data['startDate']);
        $this->assertSame('2024-06-30 23:59:59', $data['endDate']);
    }

    public function test_fetch_transactions_sends_get_transactions_request_and_parses_rows()
    {
        $this->setMockHttpResponse('FetchTransactionsSuccess.txt');

        /** @var FetchTransactionsResponse $response */
        $response = $this->gateway->fetchTransactions($this->options() + ['testMode' => true])->send();

        $this->assertInstanceOf(FetchTransactionsResponse::class, $response);
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('Successfull', $response->getCode());

        $transactions = $response->getTransactions();
        $this->assertCount(1, $transactions);
        $this->assertSame(203418187, $transactions[0]->OrderId);
        $this->assertSame('KT-ORDER-001', $transactions[0]->MerchantOrderId);

        $requests = $this->getMockedRequests();
        $httpRequest = $requests[0];
        $this->assertSame('POST', $httpRequest->getMethod());
        $this->assertStringContainsString(
            'boatest.kuveytturk.com.tr/boa.virtualpos.services/KTPay/GetTransactions',
            (string) $httpRequest->getUri(),
        );

        $body = json_decode((string) $httpRequest->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('KT-ORDER-001', $body['merchantOrderId']);
    }

    public function test_fetch_transactions_parses_empty_result()
    {
        $this->setMockHttpResponse('FetchTransactionsEmpty.txt');

        $response = $this->gateway->fetchTransactions($this->options())->send();

        $this->assertTrue($response->isSuccessful());
        $this->assertSame([], $response->getTransactions());
    }

    public function test_fetch_transactions_uses_prod_endpoint_when_test_mode_false()
    {
        $this->setMockHttpResponse('FetchTransactionsSuccess.txt');

        $this->gateway->fetchTransactions($this->options() + ['testMode' => false])->send();

        $requests = $this->getMockedRequests();

        $this->assertStringContainsString(
            'sanalpos.kuveytturk.com.tr/ServiceGateWay/KTPay/GetTransactions',
            (string) $requests[0]->getUri(),
        );
    }

    public function test_fetch_transactions_request_validation_error()
    {
        $request = new FetchTransactionsRequest($this->getHttpClient(), $this->getHttpRequest());
        $request->initialize([
            'merchantId' => '496',
            'customerId' => '400235',
            'userName' => 'apiuser',
            'password' => 'Api123',
        ]);

        $this->expectException(InvalidRequestException::class);

        $request->getData();
    }
}
