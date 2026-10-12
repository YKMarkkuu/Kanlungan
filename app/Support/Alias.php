<?php

namespace App\Support;

/**
 * Per-thread alias, e.g. "Anonymous Falcon #4821".
 *
 * The same person always gets the same alias inside one post (so people can
 * follow who is who), but a different alias in every other post. It is
 * derived from the app key, so nothing extra is stored and nobody can work
 * out the user id from the alias without the key.
 */
class Alias
{
    private const ANIMALS = [
        'Falcon', 'Owl', 'Heron', 'Eagle', 'Tarsier', 'Carabao', 'Kingfisher', 'Dove',
        'Sparrow', 'Parrot', 'Hawk', 'Lark', 'Swallow', 'Crane', 'Egret', 'Maya',
    ];

    public static function for(int $userId, int $postId): string
    {
        $hash = hexdec(substr(hash('sha256', $userId.'|'.$postId.'|'.config('app.key')), 0, 8));

        $animal = self::ANIMALS[$hash % count(self::ANIMALS)];
        $number = 1000 + intdiv($hash, count(self::ANIMALS)) % 9000;

        return "Anonymous {$animal} #{$number}";
    }
}
