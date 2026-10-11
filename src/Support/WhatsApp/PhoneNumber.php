<?php

namespace Alimarchal\LaravelChartOfAccounts\Support\WhatsApp;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;

/**
 * Turns the number as people write it (+92 300 1234567, 0300-1234567, 0092 300 1234567) into what WhatsApp wants: digits only,
 * country code first, no plus and no leading zeros ("923001234567").
 */
final class PhoneNumber
{
    public static function normalise(string $input, ?string $defaultCountryCode = null): string
    {
        $defaultCountryCode ??= (string) config('accounting.whatsapp.default_country_code', '');
        $digits = (string) preg_replace('/\D+/', '', $input);

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);                                   // 0092 300 … → 92 300 …
        } elseif (! str_starts_with(trim($input), '+') && str_starts_with($digits, '0') && $defaultCountryCode !== '') {
            $digits = $defaultCountryCode.substr($digits, 1);               // 0300 1234567 → 92 300 1234567
        }

        if (strlen($digits) < 8 || strlen($digits) > 15 || str_starts_with($digits, '0')) {
            throw new AccountingException("'{$input}' is not a phone number WhatsApp can use: write it with the country code, for example +92 300 1234567.");
        }

        return $digits;
    }
}
