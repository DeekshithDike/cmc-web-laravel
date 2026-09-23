<?php

namespace App\Support;

/**
 * HMAC for the VPS crontab scripts. The standalone PHP files must build the same string.
 */
class ServerJobSignature
{
    public const TIME_HEADER = 'X-Cm-Time';

    public const MAC_HEADER = 'X-Cm-Mac';

    public static function sign(string $secret, string $timestamp, string $path, string $body): string
    {
        $path = trim($path, '/');

        return hash_hmac('sha256', $timestamp."\n".$path."\n".$body, $secret);
    }
}
