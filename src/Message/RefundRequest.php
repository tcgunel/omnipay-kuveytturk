<?php

namespace Omnipay\KuveytTurk\Message;

use Omnipay\KuveytTurk\Constants\SaleReversalType;
use Omnipay\KuveytTurk\Helpers\Helper;

/**
 * İade / Kısmi İade — full or partial refund via /KTPay/SaleReversal.
 *
 * Omit `amount` for a full refund: KTPay sends full refunds (DRAWBACK) with
 * the amount "0" and refunds the remaining sale amount — passing an amount
 * would turn the operation into a partial refund (PARTIAL_DRAWBACK), which is
 * what the official SDKs key the type choice on.
 *
 * The sale is addressed by the bank-side `orderId` (Result_OrderId from the 3D
 * callback) and the merchant order id (`transactionId`); the package scales a
 * decimal amount to kuruş internally (12.34 → "1234").
 */
class RefundRequest extends AbstractSaleReversalRequest
{
    protected function getSaleReversalType(): int
    {
        return $this->isPartial() ? SaleReversalType::PARTIAL_DRAWBACK : SaleReversalType::DRAWBACK;
    }

    protected function getReversalAmount(): string
    {
        return $this->isPartial() ? Helper::formatAmount($this->getAmountInteger()) : '0';
    }

    private function isPartial(): bool
    {
        return $this->getAmountInteger() > 0;
    }
}
