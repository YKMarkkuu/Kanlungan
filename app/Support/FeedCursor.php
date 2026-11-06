<?php

namespace App\Support;

/**
 * The "Load more" cursor: which frozen snapshot, and where in it. URL-safe and
 * validated, so a tampered value just means "start from page 1".
 */
final class FeedCursor
{
    public static function encode(string $token, int $offset): string
    {
        return rtrim(strtr(base64_encode($token.'.'.$offset), '+/', '-_'), '=');
    }

    /** @return array{token: string, offset: int}|null */
    public static function decode(?string $raw): ?array
    {
        if ($raw === null || $raw === '' || strlen($raw) > 64) {
            return null;
        }

        $decoded = base64_decode(strtr($raw, '-_', '+/'), true);

        if ($decoded === false || ! preg_match('/^([A-Za-z0-9]{16})\.(\d{1,6})$/', $decoded, $m)) {
            return null;
        }

        return ['token' => $m[1], 'offset' => (int) $m[2]];
    }
}
