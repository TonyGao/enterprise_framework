<?php

namespace App\Service\Platform\Localization;

use Symfony\Component\Intl\Currencies;

/**
 * 本地化格式化服务 / Localization formatting service.
 *
 * 基于 PHP Intl（NumberFormatter/IntlDateFormatter），按 locale 格式化
 * 金额、数字与日期，支撑多语言/多币种展示。
 * Based on PHP Intl (NumberFormatter/IntlDateFormatter), formats money,
 * numbers and dates per locale for multi-language/multi-currency display.
 */
class MoneyFormatter
{
    /**
     * 按 locale + 币种格式化金额 /
     * Format an amount per locale and currency.
     *
     * @param string|int|float|null $amount   金额（主单位）/
     *                                        Amount (major units)
     * @param string                $currency ISO 4217 币种代码
     * @param string                $locale   BCP47/下划线 locale（如 zh_CN、en）
     * @param bool                  $symbol   是否显示货币符号（默认显示）
     */
    public function format(
        string|int|float|null $amount,
        string $currency = 'CNY',
        string $locale = 'zh_CN',
        bool $symbol = true,
    ): string {
        if ($amount === null || $amount === '') {
            return '';
        }

        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        if (!$symbol) {
            // 仅数值格式（保留币种精度）/ Numeric only (keep currency precision)
            $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
            $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $this->decimals($currency));
        }

        return $formatter->formatCurrency((float) $amount, $currency) ?: (string) $amount;
    }

    /**
     * 格式化数字（千分位、小数位随 locale）/
     * Format a number (grouping & decimals follow the locale).
     */
    public function number(string|int|float|null $number, string $locale = 'zh_CN', int $decimals = 2): string
    {
        if ($number === null || $number === '') {
            return '';
        }
        $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $decimals);
        return $formatter->format((float) $number) ?: (string) $number;
    }

    /**
     * 获取 ISO 4217 币种的小数位（默认 2）/
     * Decimal digits for an ISO 4217 currency (default 2).
     */
    public function decimals(string $currency): int
    {
        try {
            return Currencies::getFractionDigits(strtoupper($currency));
        } catch (\Exception) {
            return 2;
        }
    }

    /**
     * 获取币种符号 / Currency symbol.
     */
    public function symbol(string $currency): string
    {
        try {
            return Currencies::getSymbol(strtoupper($currency));
        } catch (\Exception) {
            return '';
        }
    }

    /**
     * 格式化日期时间（按 locale 与可选时区）/
     * Format a date-time per locale and optional timezone.
     */
    public function date(
        ?\DateTimeInterface $date,
        string $locale = 'zh_CN',
        ?string $timezone = null,
        int $dateType = \IntlDateFormatter::MEDIUM,
        int $timeType = \IntlDateFormatter::NONE,
    ): string {
        if (!$date) {
            return '';
        }
        $tz = $timezone ? new \DateTimeZone($timezone) : null;
        $formatter = new \IntlDateFormatter($locale, $dateType, $timeType, $tz);
        return $formatter->format($date) ?: $date->format('Y-m-d');
    }
}
