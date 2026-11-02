<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\User;

/** Creates in-app alerts. Alerts never contain who wrote a comment, only what was said. */
class AlertService
{
    public function notify(int $userId, string $kind, string $title, ?string $body = null, ?string $url = null): void
    {
        if (User::whereKey($userId)->whereNotNull('deleted_at')->exists()) {
            return;
        }

        Alert::create([
            'user_id' => $userId,
            'kind' => $kind,
            'title' => $title,
            'body' => $body !== null ? mb_substr($body, 0, 500) : null,
            'url' => $url,
        ]);
    }

    /** @param  array<int, string>  $roles */
    public function notifyRoles(array $roles, string $kind, string $title, ?string $body = null, ?string $url = null): void
    {
        foreach (User::whereIn('role', $roles)->pluck('id') as $id) {
            $this->notify((int) $id, $kind, $title, $body, $url);
        }
    }
}
