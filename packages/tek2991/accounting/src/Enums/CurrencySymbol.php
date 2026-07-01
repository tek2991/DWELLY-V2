<?php

namespace Tek2991\Accounting\Enums;

enum CurrencySymbol: string
{
    case USD = '$';
    case EUR = '€';
    case GBP = '£';
    case INR = '₹';
    case AUD = 'A$';
    case CAD = 'C$';
    case JPY = '¥';
    case CNY = 'CN¥';
    case SGD = 'S$';
    case CHF = 'CHF';
    case AED = 'د.إ';
    case SAR = 'ر.س';

    public static function getSymbol(?string $code): string
    {
        if (!$code) {
            return '';
        }
        $code = strtoupper($code);
        foreach (self::cases() as $case) {
            if ($case->name === $code) {
                return $case->value;
            }
        }
        return $code;
    }
}
