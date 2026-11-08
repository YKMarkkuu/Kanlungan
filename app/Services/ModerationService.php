<?php

namespace App\Services;

use App\Services\FeedRankingService;
use App\Models\ModAction;
use App\Models\Report;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staff decisions on reported posts and comments. Every action needs a reason and is written to mod_actions.
 *
 * Roles: admin and guidance can both decide on any reported case (warn, remove, mute, ban, dismiss).
 * moderator is the older, narrower role: same decisions, but it can never reveal who someone is.
 * Reading the queue lives in StaffDashboardService; this class only changes things.
 *
 * Staff see reported content under its anonymous alias. Revealing the real person (reveal()) is limited
 * to admin / guidance / dpo, needs a reason, and is written to the same audit log.
 */
class ModerationService
{
    public const MUTE_DAYS = 7;

    public const STAFF = ['moderator', 'admin', 'guidance'];

    /** Roles that may reveal the real identity behind a reported post, comment or report. */
    public const REVEAL_ROLES = ['admin', 'guidance', 'dpo'];

    /** Mute lengths staff can pick, in days. The first listed is not special; MUTE_DAYS is the default. */
    public const MUTE_CHOICES = [1, 3, 7, 14, 30];

    /**
     * true: guidance decides on cases the same way admin does (what the school asked for).
     * false: guidance can only mark referred cases as handled.
     */
    public const GUIDANCE_CAN_DECIDE = true;

    public function __construct(private AlertService $alerts, private ImageService $images, private FeedRankingService $ranking)
    {
    }

    public function isStaff(?string $role): bool
    {
        return in_array($role, self::STAFF, true);
    }

    /** @return array<int, string> actions this role may run on this card */
    public function actionsFor(string $role, Report $report): array
    {
        $subjectUser = $report->mod_subject === 'reporter'
            ? $report->reporter
            : $report->reportable?->author;

        // A deleted account can no longer be warned, muted or banned: the case can only be closed.
        if ($subjectUser?->deleted_at !== null) {
            return $role === 'guidance' ? [] : ['dismiss'];
        }

        if ($role === 'guidance' && ! self::GUIDANCE_CAN_DECIDE) {
            return $report->referred_guidance_at !== null && $report->guidance_handled_at === null ? ['handled'] : [];
        }

        $actions = $report->mod_subject === 'reporter'
            ? ['warn', 'mute', 'ban', 'dismiss']
            : ['warn', 'remove', 'mute', 'ban', 'dismiss'];

        if ($subjectUser?->banned_at !== null) {
            $actions[] = 'unban';
        }

        if ($subjectUser?->muted_until !== null && Carbon::parse($subjectUser->muted_until)->isFuture()) {
            $actions[] = 'unmute';
        }

        if ($report->mod_subject !== 'reporter' && $report->reportable?->removed_at !== null) {
            $actions = array_values(array_diff($actions, ['remove']));

            $target = $report->reportable;
            $authorWiped = $target->removed_by === 'author'
                || $target->body === AccountDeletionService::TOMBSTONE_BODY;

            if (! $authorWiped) {
                $actions[] = 'restore';
            }
        }

        if ($report->referred_guidance_at === null && $report->mod_subject !== 'reporter' && $role !== 'guidance') {
            $actions[] = 'refer';
        }

        if (in_array($role, ['admin', 'guidance'], true) && $report->referred_guidance_at !== null && $report->guidance_handled_at === null) {
            $actions[] = 'handled';
        }

        return $actions;
    }

    public function act(User $actor, int $reportId, string $action, string $reason, ?string $overrideCategory = null, ?int $muteDays = null): void
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 3) {
            throw $this->error('Every action needs a reason (at least 3 characters).');
        }

        $muteDays ??= self::MUTE_DAYS;

        if ($action === 'mute' && ! in_array($muteDays, self::MUTE_CHOICES, true)) {
            throw $this->error('Pick one of the mute lengths from the list.');
        }

        DB::transaction(function () use ($actor, $reportId, $action, $reason, $overrideCategory, $muteDays) {
            $report = Report::lockForUpdate()->with('reportable')->findOrFail($reportId);

            if ($action === 'restore'
                && $report->mod_subject !== 'reporter'
                && $report->reportable?->removed_at !== null
                && ($report->reportable->removed_by === 'author'
                    || $report->reportable->body === AccountDeletionService::TOMBSTONE_BODY)) {
                throw $this->error('The author deleted this themselves, so it cannot be restored.');
            }

            if (! in_array($action, $this->actionsFor((string) $actor->role, $report), true)) {
                throw $this->error('That action is not available for this case.');
            }

            if ($overrideCategory !== null && $overrideCategory !== $report->category) {
                $report->category = $overrideCategory;
                $report->save();
            }

            if (in_array($report->category, Report::CRITICAL, true)
                && in_array($report->status, [Report::OPEN, Report::VOTING], true)) {
                $report->forceFill([
                    'status' => Report::ESCALATED,
                    'mod_status' => 'pending',
                    'mod_subject' => 'content',
                    'voting_started_at' => null,
                    'voting_ends_at' => null,
                ])->save();
            }

            $target = $report->reportable;
            $subjectUserId = $report->mod_subject === 'reporter' ? (int) $report->reporter_id : (int) $target?->author_id;
            $kind = $target instanceof \App\Models\Post ? 'post' : 'comment';
            $snippet = $target?->body;
            $changes = [];

            if (in_array($action, ['mute', 'ban'], true)
                && in_array(User::whereKey($subjectUserId)->value('role'), ['moderator', 'guidance', 'admin'], true)) {
                throw $this->error('Higher-privilege accounts cannot be muted or banned.');
            }

            switch ($action) {
                case 'warn':
                    User::whereKey($subjectUserId)->increment('warnings_count');
                    $this->alerts->notify($subjectUserId, 'notice', 'Notice from moderators', "Warning: {$reason}", snippet: $snippet);
                    $changes['mod_status'] = 'reviewed';
                    break;

                case 'remove':
                    $target?->forceFill(['removed_at' => now(), 'removed_by' => 'moderator'])->save();

                    if ($target !== null) {
                        $this->ranking->recomputeEngagement($target instanceof \App\Models\Post ? (int) $target->id : (int) $target->post_id);
                    }

                    if ($target instanceof \App\Models\Post) {
                        $this->images->deletePostImages($target);
                    }

                    $selfHarm = $report->category === 'Self-harm';
                    $message = $selfHarm
                        ? 'We removed your content because it may involve self-harm. You are not in trouble, and you do not have to handle this alone. Please see the Help page for support resources.'
                        : "Your {$kind} was removed by moderators. Reason: {$reason}";
                    $this->alerts->notify(
                        $subjectUserId,
                        'notice',
                        $selfHarm ? 'Support is available' : 'Notice from moderators',
                        $message,
                        $selfHarm ? route('page.help') : null,
                        $selfHarm ? null : $snippet,
                    );
                    $changes['mod_status'] = 'reviewed';
                    break;

                case 'restore':
                    $target?->forceFill(['removed_at' => null, 'removed_by' => null])->save();
                    if ($target !== null) {
                        $this->ranking->recomputeEngagement($target instanceof \App\Models\Post ? (int) $target->id : (int) $target->post_id);
                    }
                    $this->alerts->notify($subjectUserId, 'notice', 'Notice from moderators', "Your {$kind} was restored by moderators. Reason: {$reason}", snippet: $snippet);
                    $changes['mod_status'] = 'reviewed';
                    break;

                case 'mute':
                    User::whereKey($subjectUserId)->update(['muted_until' => now()->addDays($muteDays)]);
                    $this->alerts->notify($subjectUserId, 'notice', 'Notice from moderators', "You are muted for {$muteDays} ".($muteDays === 1 ? 'day' : 'days')." (you can read, but not post, comment, report or vote). Reason: {$reason}", snippet: $snippet);
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
                    $this->alerts->notifyRoles(['guidance', 'admin'], 'notice', 'Referred case', "A {$kind} reported for {$report->category} was referred to Guidance.", route('staff.reports', ['view' => 'referred']));
                    break;

                case 'handled':
                    $changes['guidance_handled_at'] = now();
                    break;
            }

            if ($report->status === Report::ESCALATED && ($changes['mod_status'] ?? null) === 'reviewed') {
                $changes['status'] = Report::RESOLVED;
                $changes['outcome'] = 'moderator_reviewed';
                $changes['resolved_at'] = now();
                $changes['voting_ends_at'] = $changes['resolved_at'];
            }

            // Tell the reporter once a moderator has closed the case (not for a referral or a guidance note).
            if (($changes['mod_status'] ?? null) === 'reviewed' && $report->mod_subject !== 'reporter') {
                $this->alerts->notify((int) $report->reporter_id, 'report', 'Report update', 'Your report was reviewed by a moderator.', snippet: $snippet);
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

    /**
     * Reveal who is behind a reported post / comment (or behind a false report). Needs a reason and is
     * written to mod_actions as action "unmask", so every look at an identity can be audited later.
     *
     * @return array{name: string, email: string, student_id: ?string}
     */
    public function reveal(User $actor, int $reportId, string $reason): array
    {
        if (! in_array($actor->role, self::REVEAL_ROLES, true)) {
            throw $this->error('Your account cannot reveal identities.');
        }

        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            throw $this->error('Say why you need to know who this is (at least 10 characters). This is saved in the audit log.');
        }

        return DB::transaction(function () use ($actor, $reportId, $reason) {
            $report = Report::with(['reportable.author', 'reporter'])->findOrFail($reportId);
            $targetUser = $report->mod_subject === 'reporter'
                ? $report->reporter
                : $report->reportable?->author;

            if (! $targetUser) {
                throw $this->error('The user for this report could not be found.');
            }

            if ($targetUser->deleted_at !== null) {
                throw $this->error('This account has been deleted and can no longer be unmasked.');
            }

            ModAction::create([
                'report_id' => $report->id,
                'moderator_id' => $actor->id,
                'target_user_id' => $targetUser->id,
                'action' => 'unmask',
                'reason' => $reason,
            ]);

            return [
                'name' => (string) ($targetUser->display_name ?: $targetUser->name),
                'email' => (string) $targetUser->email,
                'student_id' => $targetUser->student_id,
            ];
        });
    }

    /* ---------- internals ---------- */

    private function error(string $message): ValidationException
    {
        return ValidationException::withMessages(['report' => $message]);
    }
}
