<?php

namespace Omnipay\KuveytTurk\Tests\Feature;

use Omnipay\KuveytTurk\Helpers\Helper;
use Omnipay\KuveytTurk\Tests\TestCase;

class HelperTest extends TestCase
{
    public function test_hash_sha1_base64()
    {
        $expected = base64_encode(sha1('test', true));

        $this->assertSame($expected, Helper::hashSha1Base64('test'));
    }

    public function test_hash_sha1_base64_password_is_20_byte_sha1()
    {
        $password = 'Api123';
        $result = Helper::hashSha1Base64($password);

        $this->assertNotEmpty($result);
        $this->assertIsString($result);

        $decoded = base64_decode($result, true);
        $this->assertNotFalse($decoded);
        $this->assertSame(20, strlen($decoded));
    }

    public function test_hash_kt_pay_uses_hmac_sha512_with_sha1_password_as_key()
    {
        $hashValue = '496ORDER-0011234apiuser';
        $password = 'Api123';

        $hashPassword = base64_encode(sha1($password, true));
        $expected = base64_encode(hash_hmac('sha512', $hashValue . $hashPassword, $hashPassword, true));

        $this->assertSame($expected, Helper::hashKTPay($hashValue, $password));
    }

    public function test_hash_non3d_concatenates_fields_correctly()
    {
        $hash = Helper::hashNon3D('496', 'ORDER-001', '1234', 'apiuser', 'Api123');

        $this->assertNotEmpty($hash);

        $expected = Helper::hashKTPay('496' . 'ORDER-001' . '1234' . 'apiuser', 'Api123');

        $this->assertSame($expected, $hash);
    }

    public function test_hash_3d_concatenates_fields_correctly()
    {
        $hash = Helper::hash3D(
            '496',
            'ORDER-001',
            '1234',
            'https://example.com/success',
            'https://example.com/failure',
            'apiuser',
            'Api123',
        );

        $this->assertNotEmpty($hash);

        $expected = Helper::hashKTPay(
            '496' . 'ORDER-001' . '1234' . 'https://example.com/success' . 'https://example.com/failure' . 'apiuser',
            'Api123',
        );

        $this->assertSame($expected, $hash);
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
            'Api123',
        );

        $this->assertNotSame($hashNon3D, $hash3D);
    }

    public function test_hash_sale_reversal_concatenates_merchant_order_amount_username()
    {
        $hash = Helper::hashSaleReversal('496', 'KT-ORDER-001', '1234', 'apiuser', 'Api123');

        $expected = Helper::hashKTPay('496' . 'KT-ORDER-001' . '1234' . 'apiuser', 'Api123');

        $this->assertSame($expected, $hash);

        // Independently pinned vector (python3 hashlib/hmac) for the cancel /
        // full-refund shape the official SDKs send: amount "0".
        $this->assertSame(
            'Em9ZrQpdBxD/vXSrhJvoikcyEdkCKYqSv6qxrFcet16V1U6gZoiMkUu6s5drZqYjyg6SOyUI0ZHKNogV52ZqIg==',
            Helper::hashSaleReversal('496', 'KT-ORDER-001', '0', 'apiuser', 'Api123'),
        );
    }

    public function test_hash_sale_reversal_differs_by_amount()
    {
        $full = Helper::hashSaleReversal('496', 'KT-ORDER-001', '0', 'apiuser', 'Api123');
        $partial = Helper::hashSaleReversal('496', 'KT-ORDER-001', '1234', 'apiuser', 'Api123');

        $this->assertNotSame($full, $partial);
    }

    public function test_hash_fetch_transactions_concatenates_merchant_order_username()
    {
        $hash = Helper::hashFetchTransactions('496', 'KT-ORDER-001', 'apiuser', 'Api123');

        $expected = Helper::hashKTPay('496' . 'KT-ORDER-001' . 'apiuser', 'Api123');

        $this->assertSame($expected, $hash);

        // Independently pinned vector (python3 hashlib/hmac).
        $this->assertSame(
            '65J37OOJ3R/Gq/ipxEdVtEDVEObSWD4EgsBLprhevFelgzxBccfQ/DEnpWlFFiEaCGuCaFMBkaXc8mlnlaDjug==',
            $hash,
        );
    }

    public function test_format_amount()
    {
        $this->assertSame('100', Helper::formatAmount(100));
        $this->assertSame('1234', Helper::formatAmount(1234));
        $this->assertSame('0', Helper::formatAmount(0));
        $this->assertSame('999999', Helper::formatAmount(999999));
    }

    public function test_array_to_xml_renders_root_and_leaves()
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

    public function test_xml_string_to_object_round_trips_basic_xml()
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<VPosTransactionResponseContract>'
            . '<ResponseCode>00</ResponseCode>'
            . '<ResponseMessage>Onaylandi</ResponseMessage>'
            . '<MerchantOrderId>ORDER-001</MerchantOrderId>'
            . '</VPosTransactionResponseContract>';

        $obj = Helper::xmlStringToObject($xml);

        $this->assertIsObject($obj);
        $this->assertSame('00', $obj->ResponseCode);
        $this->assertSame('Onaylandi', $obj->ResponseMessage);
        $this->assertSame('ORDER-001', $obj->MerchantOrderId);
    }

    public function test_json_string_to_object()
    {
        $obj = Helper::jsonStringToObject('{"ResponseCode":"00","ResponseMessage":"Onaylandi"}');

        $this->assertIsObject($obj);
        $this->assertSame('00', $obj->ResponseCode);
        $this->assertSame('Onaylandi', $obj->ResponseMessage);
    }

    public function test_json_string_to_object_returns_null_on_invalid_json()
    {
        $this->assertNull(Helper::jsonStringToObject('not json'));
        $this->assertNull(Helper::jsonStringToObject('"just a string"'));
        $this->assertNull(Helper::jsonStringToObject('[1,2,3]'));
    }

    public function test_tr_subscriber_strips_country_and_trunk_prefixes()
    {
        $this->assertSame('5551112233', Helper::trSubscriber('+905551112233'));
        $this->assertSame('5551112233', Helper::trSubscriber('905551112233'));
        $this->assertSame('5551112233', Helper::trSubscriber('05551112233'));
        $this->assertSame('5551112233', Helper::trSubscriber('5551112233'));
        $this->assertSame('5551112233', Helper::trSubscriber('+90 (555) 111 22 33'));
        $this->assertSame('', Helper::trSubscriber(null));
        $this->assertSame('', Helper::trSubscriber(''));
    }
}
