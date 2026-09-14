<?php

namespace App\Service\Platform\Localization;

/**
 * 金额值对象 / Money value object.
 *
 * 金额 = 数值 + 币种代码，为多币种业务提供类型安全的金额表达。
 * A monetary value = amount + currency code, providing type-safe amounts
 * for multi-currency business logic.
 */
final class Money
{
    /**
     * @param string|int|float|null $amount   金额（以主单位表示，如 1234.56）/
     *                                        Amount in major units (e.g. 1234.56)
     * @param string                $currency ISO 4217 币种代码（默认 CNY）/
     *                                        ISO 4217 currency code (default CNY)
     */
    public function __construct(
        private readonly string|int|float|null $amount,
        private readonly string $currency = 'CNY',
    ) {
    }

    public static function zero(string $currency = 'CNY'): self
    {
        return new self(0, $currency);
    }

    public function getAmount(): ?string
    {
        return $this->amount === null ? null : (string) $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * 以字符串返回金额（保留精度，不丢失小数）/
     * Return the amount as string (precision-preserving).
     */
    public function __toString(): string
    {
        return $this->getAmount() ?? '';
    }
}
