<?php

namespace Omnipay\KuveytTurk\Constants;

class CurrencyCode
{
    public const TRY = '949';

    public const USD = '840';

    public const EUR = '978';

    public const GBP = '826';

    public static function fromAlpha(string $currency): string
    {
        $map = [
            'TRY' => self::TRY,
            'USD' => self::USD,
            'EUR' => self::EUR,
            'GBP' => self::GBP,
        ];

        return $map[$currency] ?? self::TRY;
    }
}
