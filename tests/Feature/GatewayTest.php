<?php

namespace Omnipay\KuveytTurk\Tests\Feature;

use Omnipay\KuveytTurk\Message\CompletePurchaseRequest;
use Omnipay\KuveytTurk\Message\InstallmentDefinitionRequest;
use Omnipay\KuveytTurk\Message\IsOnusCardRequest;
use Omnipay\KuveytTurk\Message\PurchaseRequest;
use Omnipay\KuveytTurk\Tests\TestCase;

class GatewayTest extends TestCase
{
    public function test_gateway_name()
    {
        $this->assertSame('KuveytTurk', $this->gateway->getName());
    }

    public function test_gateway_default_parameters()
    {
        $defaults = $this->gateway->getDefaultParameters();

        $this->assertArrayHasKey('clientIp', $defaults);
        $this->assertArrayHasKey('merchantId', $defaults);
        $this->assertArrayHasKey('customerId', $defaults);
        $this->assertArrayHasKey('userName', $defaults);
        $this->assertArrayHasKey('password', $defaults);
        $this->assertArrayHasKey('installment', $defaults);
        $this->assertArrayHasKey('secure', $defaults);

        $this->assertSame('127.0.0.1', $defaults['clientIp']);
        $this->assertTrue($defaults['secure']);
    }

    public function test_gateway_purchase_returns_correct_request()
    {
        $this->assertInstanceOf(PurchaseRequest::class, $this->gateway->purchase([]));
    }

    public function test_gateway_complete_purchase_returns_correct_request()
    {
        $this->assertInstanceOf(CompletePurchaseRequest::class, $this->gateway->completePurchase([]));
    }

    public function test_gateway_is_onus_card_returns_correct_request()
    {
        $this->assertInstanceOf(IsOnusCardRequest::class, $this->gateway->isOnusCard([]));
    }

    public function test_gateway_installment_definition_returns_correct_request()
    {
        $this->assertInstanceOf(InstallmentDefinitionRequest::class, $this->gateway->installmentDefinition([]));
    }

    public function test_gateway_getters_setters()
    {
        $this->gateway->setMerchantId('496');
        $this->assertSame('496', $this->gateway->getMerchantId());

        $this->gateway->setCustomerId('400235');
        $this->assertSame('400235', $this->gateway->getCustomerId());

        $this->gateway->setUserName('apiuser');
        $this->assertSame('apiuser', $this->gateway->getUserName());

        $this->gateway->setPassword('Api123');
        $this->assertSame('Api123', $this->gateway->getPassword());

        $this->gateway->setInstallment(3);
        $this->assertSame(3, $this->gateway->getInstallment());

        $this->gateway->setSecure(false);
        $this->assertFalse($this->gateway->getSecure());

        $this->gateway->setClientIp('192.168.1.1');
        $this->assertSame('192.168.1.1', $this->gateway->getClientIp());

        $this->gateway->setMd('SOME-MD');
        $this->assertSame('SOME-MD', $this->gateway->getMd());

        $this->gateway->setCardBin('540061');
        $this->assertSame('540061', $this->gateway->getCardBin());
    }
}
