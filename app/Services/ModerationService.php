<?php

namespace App\Services;

use App\Models\ModAction;
use App\Models\Report;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moderator queue (design screen 9). Every action needs a reason and is written to mod_actions.
 *
 * Roles: moderator / admin act on the queue. guidance only sees cases referred to Guidance and can
 * mark them handled. Moderators see real names (the system always knows who posted).
 */
class ModerationService
{
    public const MUTE_DAYS = 7;

    public const STAFF = ['moderator', 'admin', 'guidance'];

    public function __construct(private AlertService $alerts, private ImageService $images)
    {
    }

    public function isStaff(?string $role): bool
    {
        return in_array($role, self::STAFF, true);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function queue(string $tab, ?string $severity): Collection
    {
        $query = Report::query()->with([
            'reportable.author:id,display_name,banned_at,muted_until',
            'reporter:id,display_name,banned_at,muted_until',
        ])->withCount([
            'votes',
            'votes as agree_count' => fn ($q) => $q->where('choice', 'agree'),
        ]);

        if ($tab === 'guidance') {
            $query->whereNotNull('referred_guidance_at')->whereNull('guidance_handled_at');
        } else {
            $query->where('mod_status', 'pending');
        }

        if ($severity !== null) {
            $categories = [
                'critical' => Report::CRITICAL,
                'high' => Report::HIGH,
                'medium' => Report::MEDIUM,
            ][$severity] ?? [];
            $query->whereIn('category', $categories);
        }

        $criticalPlaceholders = implode(',', array_fill(0, count(Report::CRITICAL), '?'));
        $highPlaceholders = implode(',', array_fill(0, count(Report::HIGH), '?'));
        $mediumPlaceholders = implode(',', array_fill(0, count(Report::MEDIUM), '?'));

        return $query
            ->orderByRaw(
                "CASE WHEN category IN ({$criticalPlaceholders}) THEN 1
                    WHEN category IN ({$highPlaceholders}) THEN 2
                    WHEN category IN ({$mediumPlaceholders}) THEN 3
                    ELSE 4 END",
                [...Report::CRITICAL, ...Report::HIGH, ...Report::MEDIUM],
            )
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (Report $r) => $this->card($r));
    }

    /** @return Collection<int, array<string, mixed>> */
    public function history(): Collection
    {
        return ModAction::query()->with(['moderator:id,name', 'report:id,category'])->latest()->latest('id')->limit(50)->get()
            ->map(fn (ModAction $a) => [
                'action' => $a->action,
                'reason' => $a->reason,
                'moderator' => $a->moderator?->name ?? 'Moderator',
                'category' => $a->report?->category,
                'time' => $a->created_at?->diffForHumans(),
            ]);
    }

    /** @return array<int, string> actions this role may run on this card */
    public function actionsFor(string $role, Report $report): array
    {
        if ($role === 'guidance') {
            return $report->referred_guidance_at !== null && $report->guidance_handled_at === null ? ['handled'] : [];
        }

        $actions = $report->mod_subject === 'reporter'
            ? ['warn', 'mute', 'ban', 'dismiss']
            : ['warn', 'remove', 'mute', 'ban', 'dismiss'];
        $subjectUser = $report->mod_subject === 'reporter'
            ? $report->reporter
            : $report->reportable?->author;

        if ($subjectUser?->banned_at !== null) {
            $actions[] = 'unban';
        }

        if ($subjectUser?->muted_until !== null && Carbon::parse($subjectUser->muted_until)->isFuture()) {
            $actions[] = 'unmute';
        }

        if ($report->mod_subject !== 'reporter' && $report->reportable?->removed_at !== null) {
            $actions = array_values(array_diff($actions, ['remove']));
            $actions[] = 'restore';
        }

        if ($report->referred_guidance_at === null && $report->mod_subject !== 'reporter') {
            $actions[] = 'refer';
        }

        if ($role === 'admin' && $report->referred_guidance_at !== null && $report->guidance_handled_at === null) {
            $actions[] = 'handled';
        }

        return $actions;
    }

    public function act(User $actor, int $reportId, string $action, string $reason, ?string $overrideCategory = null): void
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 3) {
            throw $this->error('Every action needs a reason (at least 3 characters).');
        }

        DB::transaction(function () use ($actor, $reportId, $action, $reason, $overrideCategory) {
            $report = Report::lockForUpdate()->with('reportable')->findOrFail($reportId);

            if (! in_array($action, $this->actionsFor((string) $actor->role, $report), true)) {
                throw $this->error('That action is not available for this case.');
            }

            if ($overrideCategory !== null && $overrideCategory !== $report->category) {
                $report->category = $overrideCategory;
                $report->save();
            }

            $target = $report->reportable;
            $subjectUserId = $report->mod_subject === 'reporter' ? (int) $report->reporter_id : (int) $target?->author_id;
            $kind = $target instanceof \App\Models\Post ? 'post' : 'comment';
            $changes = [];

            if (in_array($action, ['mute', 'ban'], true)
                && in_array(User::whereKey($subjectUserId)->value('role'), ['moderator', 'guidance', 'admin'], true)) {
                throw $this->error('Higher-privilege accounts cannot be muted or banned.');
            }

            switch ($action) {
                case 'warn':
                    User::whereKey($subjectUserId)->increment('warnings_count');
                    $this->alerts->notify($subjectUserId, 'notice', 'Notice from moderators', "Warning: {$reason}");
                    $changes['mod_status'] = 'reviewed';
                    break;

                case 'remove':
                    $target?->forceFill(['removed_at' => now(), 'removed_by' => 'moderator'])->save();

                    if ($target instanceof \App\Models\Post) {
                        $this->images->deletePostImages($target);
                    }

                    $this->alerts->notify($subjectUserId, 'notice', 'Notice from moderators', "Your {$kind} was removed by moderators. Reason: {$reason}");
                    $changes['mod_status'] = 'reviewed';
                    break;

                case 'restore':
                    $target?->forceFill(['removed_at' => null, 'removed_by' => null])->save();
                    $this->alerts->notify($subjectUserId, 'notice', 'Notice from moderators', "Your {$kind} was restored by moderators. Reason: {$reason}");
                    $changes['mod_status'] = 'reviewed';
                    break;

                case 'mute':
                    User::whereKey($subjectUserId)->update(['muted_until' => now()->addDays(self::MUTE_DAYS)]);
                    $this->alerts->notify($subjectUserId, 'notice', 'Notice from moderators', 'You are muted for '.self::MUTE_DAYS." days (you can read, but not post, comment, report or vote). Reason: {$reason}");
                    $changes['mod_status'] = 'reviewed';
                    break;

                case 'ban':
                    User::whereKey($subjectUserId)->update(['banned_at' => now()]);
                    $changes['mod_status'] = 'reviewed';
                    break;

                case 'unmute':
                    User::whereKey($subjectUserId)->update(['muted_until' => null]);
                    $changes['mod_status'] = 'reviewed';
                    break;

                case 'unban':
                    User::whereKey($subjectUserId)->update(['banned_at' => null]);
                    $changes['mod_status'] = 'reviewed';
                    break;

                case 'dismiss':
                    $dismissedAt = now();
                    $changes['status'] = Report::RESOLVED;
                    $changes['outcome'] = 'moderator_cleared';
                    $changes['resolved_at'] = $dismissedAt;
                    $changes['voting_ends_at'] = $dismissedAt;
                    $changes['mod_status'] = 'reviewed';
                    break;

                case 'refer':
                    $changes['referred_guidance_at'] = now();
                    $this->alerts->notifyRoles(['guidance', 'admin'], 'notice', 'Referred case', "A {$kind} reported for {$report->category} was referred to Guidance.", route('moderation', ['tab' => 'guidance']));
                    break;

                case 'handled':
                    $changes['guidance_handled_at'] = now();
                    break;
            }

            // Tell the reporter once a moderator has closed the case (not for a referral or a guidance note).
            if (($changes['mod_status'] ?? null) === 'reviewed' && $report->mod_subject !== 'reporter') {
                $this->alerts->notify((int) $report->reporter_id, 'report', 'Report update', 'Your report was reviewed by a moderator.');
            }

            ModAction::create([
                'report_id' => $report->id,
                'moderator_id' => $actor->id,
                'target_user_id' => $subjectUserId ?: null,
                'action' => $action,
                'reason' => $reason,
            ]);

            if ($changes !== []) {
                $report->update($changes);
            }
        });
    }

    public function unmask(User $actor, int $reportId, string $reason): string
    {
        return DB::transaction(function () use ($actor, $reportId, $reason) {
            $report = Report::with(['reportable.author', 'reporter'])->findOrFail($reportId);
            $targetUser = $report->mod_subject === 'reporter'
                ? $report->reporter
                : $report->reportable?->author;

            if (! $targetUser) {
                throw $this->error('The user for this report could not be found.');
            }

            ModAction::create([
                'report_id' => $report->id,
                'moderator_id' => $actor->id,
                'target_user_id' => $targetUser->id,
                'action' => 'unmask',
                'reason' => trim($reason),
            ]);

            return $targetUser->email;
        });
    }

    /* ---------- internals ---------- */

    /** @return array<string, mixed> */
    private function card(Report $r): array
    {
        $target = $r->reportable;
        $subjectUser = $r->mod_subject === 'reporter' ? $r->reporter : $target?->author;

        return [
            'id' => $r->id,
            'severity' => Report::severityFor($r->category),
            'category' => $r->category,
            'details' => $r->details,
            'status' => $r->status,
            'outcome' => $r->outcome,
            'votes' => (int) $r->votes_count,
            'agree' => (int) $r->agree_count,
            'kind' => $target instanceof \App\Models\Post ? 'post' : 'comment',
            'body' => $target?->body ?? '(deleted)',
            'removed' => $target?->removed_at !== null,
            'subject' => $r->mod_subject ?? 'content',
            'subject_user' => $subjectUser?->display_name ?? 'Anonymous',
            'referred' => $r->referred_guidance_at !== null,
            'handled' => $r->guidance_handled_at !== null,
            'time' => $r->created_at?->diffForHumans(),
            'model' => $r,
        ];
    }

    private function error(string $message): ValidationException
    {
        return ValidationException::withMessages(['report' => $message]);
    }
}
