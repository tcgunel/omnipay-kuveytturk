<?php

namespace Omnipay\KuveytTurk\Message;

use Omnipay\KuveytTurk\Constants\SaleReversalType;

/**
 * İptal — same-day cancellation of the whole sale via /KTPay/SaleReversal.
 *
 * The sale is addressed by the bank-side `orderId` (Result_OrderId from the 3D
 * callback) and the merchant order id (`transactionId`). Cancels are not
 * partial: the amount is sent as "0", exactly like the official SDKs.
 */
class CancelRequest extends AbstractSaleReversalRequest
{
    protected function getSaleReversalType(): int
    {
        return SaleReversalType::CANCEL;
    }

    protected function getReversalAmount(): string
    {
        return '0';
    }
}
