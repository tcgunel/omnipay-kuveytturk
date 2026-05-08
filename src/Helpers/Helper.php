<?php

namespace Omnipay\KuveytTurk\Helpers;

use SimpleXMLElement;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

class Helper
{
    /**
     * Generate SHA1 hash and return as Base64.
     */
    public static function hashSha1Base64(string $data): string
    {
        return base64_encode(sha1($data, true));
    }

    /**
     * KuveytTurk KTPay hash algorithm.
     *
     * Steps:
     *   1. hashPassword = SHA1Base64(password)
     *   2. message      = hashValue . hashPassword
     *   3. result       = Base64(HMAC-SHA512(message, key = hashPassword))
     */
    public static function hashKTPay(string $hashValue, string $password): string
    {
        $hashPassword = self::hashSha1Base64($password);

        $hmac = hash_hmac('sha512', $hashValue . $hashPassword, $hashPassword, true);

        return base64_encode($hmac);
    }

    /**
     * Hash for 3D payment request (KTPay/Payment).
     * SHA1Base64(MerchantId + MerchantOrderId + Amount + OkUrl + FailUrl + UserName + SHA1Base64(Password)) — legacy.
     * Now: HMAC-SHA512 over the same concatenation.
     */
    public static function hash3D(
        string $merchantId,
        string $merchantOrderId,
        string $amount,
        string $okUrl,
        string $failUrl,
        string $userName,
        string $password,
    ): string {
        return self::hashKTPay(
            $merchantId . $merchantOrderId . $amount . $okUrl . $failUrl . $userName,
            $password,
        );
    }

    /**
     * Hash for non-3D payment + provision (no URLs).
     */
    public static function hashNon3D(
        string $merchantId,
        string $merchantOrderId,
        string $amount,
        string $userName,
        string $password,
    ): string {
        return self::hashKTPay(
            $merchantId . $merchantOrderId . $amount . $userName,
            $password,
        );
    }

    /**
     * Build XML string from array data for KuveytTurk VPos (used for non-3D Home/Non3DPayGate).
     */
    public static function arrayToXml(array $data, string $rootElement = 'KuveytTurkVPosMessage'): string
    {
        $xml = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><{$rootElement}/>");

        self::addArrayToXml($xml, $data);

        $dom = dom_import_simplexml($xml)->ownerDocument;
        $dom->formatOutput = true;

        return $dom->saveXML();
    }

    /**
     * Recursively add array elements to a SimpleXMLElement.
     */
    private static function addArrayToXml(SimpleXMLElement $xml, array $data): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $child = $xml->addChild($key);
                self::addArrayToXml($child, $value);
            } else {
                $xml->addChild($key, htmlspecialchars((string) $value));
            }
        }
    }

    /**
     * Parse XML string to stdClass object.
     */
    public static function xmlStringToObject(string $data): object
    {
        $encoder = new XmlEncoder();
        $xml = $encoder->decode($data, 'xml');

        return (object) json_decode(json_encode($xml, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Parse JSON string to stdClass object (returns null on invalid JSON).
     */
    public static function jsonStringToObject(string $data): ?object
    {
        $decoded = json_decode($data, false);

        return is_object($decoded) ? $decoded : null;
    }

    /**
     * Strip Turkish country/trunk prefix from a phone number, returning the 10-digit subscriber portion when possible.
     */
    public static function trSubscriber(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone ?? '') ?? '';

        if (strlen($digits) === 12 && str_starts_with($digits, '90')) {
            return substr($digits, 2);
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return substr($digits, 1);
        }

        return $digits;
    }

    /**
     * Format amount for KuveytTurk: integer kuruş as string. e.g. 12.34 TRY → "1234".
     */
    public static function formatAmount(int $amountInteger): string
    {
        return (string) $amountInteger;
    }
}
