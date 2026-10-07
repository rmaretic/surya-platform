<?php

namespace App\Actions;

use InvalidArgumentException;

class NormalizeHostname
{
    public function handle(string $hostname): string
    {
        $hostname = trim($hostname);
        $hostname = str_ends_with($hostname, '.') ? substr($hostname, 0, -1) : $hostname;

        if ($hostname === '') {
            throw new InvalidArgumentException('A valid fully qualified hostname is required.');
        }

        $ascii = idn_to_ascii(
            $hostname,
            IDNA_NONTRANSITIONAL_TO_ASCII | IDNA_USE_STD3_RULES | IDNA_CHECK_BIDI | IDNA_CHECK_CONTEXTJ,
            INTL_IDNA_VARIANT_UTS46,
        );

        if ($ascii === false || strlen($ascii) > 253 || filter_var($ascii, FILTER_VALIDATE_IP)
            || ! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $ascii)) {
            throw new InvalidArgumentException('A valid fully qualified hostname is required.');
        }

        return $ascii;
    }
}
