<?php

declare(strict_types=1);

namespace Tempcord\Plugins\Http\Http;

/**
 * Comparing things an attacker gets to guess at.
 *
 * Every comparison here runs in the same time whatever the inputs. `===` on a
 * secret stops at the first byte that differs, and the difference is measurable
 * over enough requests — which is how a signature gets guessed one byte at a
 * time by somebody with patience and a loop.
 */
final class Secret
{
    /**
     * Whether two secrets are the same, without saying how far along they
     * stopped matching.
     */
    public static function matches(string $expected, string $given): bool
    {
        return $expected !== '' && hash_equals($expected, $given);
    }

    /**
     * Whether a body carries the signature it should, as a webhook sender
     * computes one: an HMAC of the raw body under a shared secret.
     *
     * The raw body, not the decoded one — re-encoding changes bytes, and the
     * signature is over what was actually sent.
     */
    public static function signed(
        string $body,
        string $signature,
        string $secret,
        string $algorithm = 'sha256',
    ): bool {
        if ($secret === '' || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac($algorithm, $body, $secret), $signature);
    }
}
