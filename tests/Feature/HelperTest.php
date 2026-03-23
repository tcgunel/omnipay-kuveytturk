<?php

namespace Omnipay\KuveytTurk\Tests\Feature;

use Omnipay\KuveytTurk\Helpers\Helper;
use Omnipay\KuveytTurk\Tests\TestCase;

class HelperTest extends TestCase
{
    public function test_hash_sha1_base64()
    {
        // SHA1 of "test" in binary, then base64 encoded
        $expected = base64_encode(sha1('test', true));

        $this->assertEquals($expected, Helper::hashSha1Base64('test'));
    }

    public function test_hash_sha1_base64_password()
    {
        // Verify SHA1Base64 of a password
        $password = 'Api123';
        $result = Helper::hashSha1Base64($password);

        $this->assertNotEmpty($result);
        $this->assertIsString($result);

        // Base64 decode should work
        $decoded = base64_decode($result, true);
        $this->assertNotFalse($decoded);

        // SHA1 produces 20 bytes
        $this->assertEquals(20, strlen($decoded));
    }

    public function test_hash_non3d()
    {
        $hash = Helper::hashNon3D('496', 'ORDER-001', '1234', 'apiuser', 'Api123');

        $this->assertNotEmpty($hash);

        // Verify it matches manual calculation
        $hashedPassword = Helper::hashSha1Base64('Api123');
        $expectedHash = Helper::hashSha1Base64('496' . 'ORDER-001' . '1234' . 'apiuser' . $hashedPassword);

        $this->assertEquals($expectedHash, $hash);
    }

    public function test_hash_3d()
    {
        $hash = Helper::hash3D(
            '496',
            'ORDER-001',
            '1234',
            'https://example.com/success',
            'https://example.com/failure',
            'apiuser',
            'Api123'
        );

        $this->assertNotEmpty($hash);

        // Verify it matches manual calculation
        $hashedPassword = Helper::hashSha1Base64('Api123');
        $expectedHash = Helper::hashSha1Base64(
            '496' . 'ORDER-001' . '1234' . 'https://example.com/success' . 'https://example.com/failure' . 'apiuser' . $hashedPassword
        );

        $this->assertEquals($expectedHash, $hash);
    }

    public function test_hash_3d_differs_from_non3d()
    {
        $hashNon3D = Helper::hashNon3D('496', 'ORDER-001', '1234', 'apiuser', 'Api123');
        $hash3D = Helper::hash3D(
            '496',
            'ORDER-001',
            '1234',
            'https://example.com/success',
            'https://example.com/failure',
            'apiuser',
            'Api123'
        );

        $this->assertNotEquals($hashNon3D, $hash3D);
    }

    public function test_format_amount()
    {
        $this->assertEquals('100', Helper::formatAmount(100));
        $this->assertEquals('1234', Helper::formatAmount(1234));
        $this->assertEquals('0', Helper::formatAmount(0));
        $this->assertEquals('999999', Helper::formatAmount(999999));
    }

    public function test_array_to_xml()
    {
        $data = [
            'APIVersion' => 'TDV2.0.0',
            'MerchantId' => '496',
            'Amount' => '1234',
        ];

        $xml = Helper::arrayToXml($data);

        $this->assertStringContainsString('KuveytTurkVPosMessage', $xml);
        $this->assertStringContainsString('<APIVersion>TDV2.0.0</APIVersion>', $xml);
        $this->assertStringContainsString('<MerchantId>496</MerchantId>', $xml);
        $this->assertStringContainsString('<Amount>1234</Amount>', $xml);
    }

    public function test_array_to_xml_with_nested_data()
    {
        $data = [
            'MerchantId' => '496',
            'KuveytTurkVPosAdditionalData' => [
                'Key' => 'MD',
                'Data' => 'somevalue',
            ],
        ];

        $xml = Helper::arrayToXml($data);

        $this->assertStringContainsString('<KuveytTurkVPosAdditionalData>', $xml);
        $this->assertStringContainsString('<Key>MD</Key>', $xml);
        $this->assertStringContainsString('<Data>somevalue</Data>', $xml);
    }

    public function test_xml_string_to_object()
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<VPosTransactionResponseContract>'
            . '<ResponseCode>00</ResponseCode>'
            . '<ResponseMessage>Onaylandi</ResponseMessage>'
            . '<MerchantOrderId>ORDER-001</MerchantOrderId>'
            . '</VPosTransactionResponseContract>';

        $obj = Helper::xmlStringToObject($xml);

        $this->assertIsObject($obj);
        $this->assertEquals('00', $obj->ResponseCode);
        $this->assertEquals('Onaylandi', $obj->ResponseMessage);
        $this->assertEquals('ORDER-001', $obj->MerchantOrderId);
    }
}
