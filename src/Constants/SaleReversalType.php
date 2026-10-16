<?php

namespace Omnipay\KuveytTurk\Constants;

/**
 * Operation type for /KTPay/SaleReversal, as defined by the official
 * KuveytTurk SDKs (ktpay-php SaleReversalType, ktpay-dotnet SaleReversalType).
 */
class SaleReversalType
{
    /** İade — full refund of the remaining sale amount. */
    public const DRAWBACK = 1;

    /** Kısmi İade — partial refund for the given amount. */
    public const PARTIAL_DRAWBACK = 2;

    /** İptal — same-day cancellation of the whole sale. */
    public const CANCEL = 3;
}
