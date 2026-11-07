<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Creates in-app alerts.
 *
 * title   = what happened ("New comment from Anonymous Falcon #4821")
 * snippet = a short quote of the comment / post it is about
 * body    = plain-text message (moderator notices, report updates)
 *
 * Alerts only ever name someone as the thread shows them (alias or display name), never the account.
 */
class AlertService
{
    public const SNIPPET_LENGTH = 120;

    public function notify(int $userId, string $kind, string $title, ?string $body = null, ?string $url = null, ?string $snippet = null): void
    {
        if (User::whereKey($userId)->whereNotNull('deleted_at')->exists()) {
            return;
        }

        Alert::create([
            'user_id' => $userId,
            'kind' => $kind,
            'title' => $title,
            'body' => $body !== null ? mb_substr($body, 0, 500) : null,
            'snippet' => $this->excerpt($snippet),
            'url' => $url,
        ]);
    }

    /** @param  array<int, string>  $roles */
    public function notifyRoles(array $roles, string $kind, string $title, ?string $body = null, ?string $url = null, ?string $snippet = null): void
    {
        foreach (User::whereIn('role', $roles)->pluck('id') as $id) {
            $this->notify((int) $id, $kind, $title, $body, $url, $snippet);
        }
    }

    /** One line, trimmed to SNIPPET_LENGTH. Blank text and erased ("[deleted]") content give null. */
    private function excerpt(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($text === '' || $text === AccountDeletionService::TOMBSTONE_BODY) {
            return null;
        }

        return Str::limit($text, self::SNIPPET_LENGTH);
    }
}
