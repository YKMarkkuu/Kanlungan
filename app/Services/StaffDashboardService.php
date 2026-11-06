<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\ModAction;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Support\Alias;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Everything the staff dashboard (/staff) reads. Admin and guidance only ever see REPORTED content.
 *
 * Privacy rules this class enforces:
 *  - Authors are shown under the alias other students saw ("Anonymous Owl #1093"), never by email or student ID.
 *  - Reporters are never named. They only appear when the community judged the report false, and even
 *    then only as "the student who reported this".
 *  - The real person behind a case is only visible to someone who revealed it with a logged reason
 *    (ModerationService::reveal), and only for REVEAL_WINDOW_MINUTES afterwards.
 */
class StaffDashboardService
{
    /** Tabs on the reports page. */
    public const VIEWS = [
        'review' => 'Needs a decision',
        'referred' => 'Referred to Guidance',
        'voting' => 'In community vote',
        'closed' => 'Closed',
        'all' => 'All reports',
    ];

    /** The app stores UTC; staff read times in Philippine time. Only used for display on /staff pages. */
    public const TIMEZONE = 'Asia/Manila';

    /** How long a revealed identity stays on screen for the person who revealed it. */
    public const REVEAL_WINDOW_MINUTES = 30;

    public const ACTION_LABELS = [
        'warn' => 'Warn',
        'remove' => 'Remove',
        'restore' => 'Restore',
        'mute' => 'Mute',
        'ban' => 'Ban',
        'unmute' => 'Unmute',
        'unban' => 'Unban',
        'dismiss' => 'No action',
        'refer' => 'Refer to Guidance',
        'handled' => 'Mark handled',
        'unmask' => 'Identity revealed',
    ];

    public function __construct(private ModerationService $moderation)
    {
    }

    /* ---------- overview ---------- */

    /** @return array<string, int> */
    public function counts(): array
    {
        $pending = Report::where('mod_status', 'pending');

        return [
            'review' => (clone $pending)->count(),
            'critical' => (clone $pending)->whereIn('category', Report::CRITICAL)->count(),
            'referred' => $this->view(Report::query(), 'referred')->count(),
            'voting' => $this->view(Report::query(), 'voting')->count(),
            'sanctions' => $this->sanctionedUsers()->count(),
            'actions_week' => ModAction::where('created_at', '>=', now()->subDays(7))->where('action', '!=', 'unmask')->count(),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function waiting(string $view = 'review', int $limit = 6): Collection
    {
        return $this->view($this->baseQuery(), $view)
            ->tap(fn (Builder $q) => $this->orderBySeverity($q))
            ->orderBy('reports.id')
            ->limit($limit)
            ->get()
            ->map(fn (Report $r) => $this->row($r));
    }

    /** Reports per reason over the last 30 days, biggest first. @return Collection<int, array{category: string, total: int, severity: string}> */
    public function reasons(): Collection
    {
        return Report::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['category' => $r->category, 'total' => (int) $r->total, 'severity' => Report::severityFor($r->category)]);
    }

    /* ---------- reports list ---------- */

    public function list(string $view, ?string $severity, ?string $type): Paginator
    {
        $query = $this->view($this->baseQuery(), $view);

        if ($severity !== null) {
            $query->whereIn('category', ['critical' => Report::CRITICAL, 'high' => Report::HIGH, 'medium' => Report::MEDIUM][$severity] ?? []);
        }

        if ($type !== null) {
            $query->where('reportable_type', $type === 'post' ? Post::class : Comment::class);
        }

        $this->orderBySeverity($query);
        // Longest-waiting first where someone has to act; newest first when just looking back.
        in_array($view, ['review', 'referred'], true) ? $query->orderBy('reports.id') : $query->orderByDesc('reports.id');

        return $query->simplePaginate(15)->withQueryString()->through(fn (Report $r) => $this->row($r));
    }

    /** @return array<string, int> number of reports in each tab, for the tab labels */
    public function tabCounts(): array
    {
        $out = [];

        foreach (array_keys(self::VIEWS) as $view) {
            $out[$view] = $this->view(Report::query(), $view)->count();
        }

        return $out;
    }

    /* ---------- one case ---------- */

    /** @return array<string, mixed> */
    public function caseFor(int $reportId, User $viewer): array
    {
        $report = $this->baseQuery()->with(['reportable.author', 'reporter'])->findOrFail($reportId);
        $target = $report->reportable;
        $aboutReporter = $report->mod_subject === 'reporter';
        $subject = $aboutReporter ? $report->reporter : $target?->author;

        if ($target instanceof Comment) {
            $target->loadMissing('post');
        }

        $prior = $this->priorActions($subject, $report->id);

        return [
            'id' => $report->id,
            'row' => $this->row($report),
            'severity' => Report::severityFor($report->category),
            'category' => $report->category,
            'details' => $report->details,
            'kind' => $target instanceof Post ? 'post' : 'comment',
            'body' => $target?->body ?? '(this content no longer exists)',
            'image' => $target instanceof Post && $target->removed_at === null && $target->image_path ? asset($target->image_path) : null,
            'context' => $target instanceof Comment && $target->post ? Str::limit($target->post->body, 300) : null,
            'posted_at' => $target?->created_at,
            'removed' => $target?->removed_at !== null,
            'removed_by' => $target?->removed_by,
            'about_reporter' => $aboutReporter,
            // Who wrote the content, as other students saw it, and who staff are actually judging.
            // They differ on a false report: the post is fine, the person judged is the reporter.
            'author_label' => $this->label($target),
            'subject_label' => $aboutReporter ? 'The student who reported this' : $this->label($target),
            'status' => $report->status,
            'outcome' => $report->outcome,
            'votes' => (int) $report->votes_count,
            'agree' => (int) $report->agree_count,
            'false_votes' => (int) $report->false_count,
            'wrong_tag_votes' => (int) $report->wrong_tag_count,
            'referred' => $report->referred_guidance_at !== null,
            'handled' => $report->guidance_handled_at !== null,
            'reported_at' => $report->created_at,
            'record' => $this->record($subject, $prior),
            'suggestion' => $this->suggest($report, $subject, $prior),
            'actions' => $this->moderation->actionsFor((string) $viewer->role, $report),
            'timeline' => $this->timeline($report->id),
            'revealed' => $this->revealedTo($viewer, $report, $subject),
            'can_reveal' => in_array($viewer->role, ModerationService::REVEAL_ROLES, true) && $subject !== null,
        ];
    }

    /* ---------- accounts under restriction ---------- */

    /** @return Collection<int, array<string, mixed>> */
    public function sanctions(): Collection
    {
        $users = $this->sanctionedUsers()->orderByDesc('id')->get();

        $last = ModAction::query()
            ->with('moderator:id,name,role')
            ->whereIn('target_user_id', $users->pluck('id'))
            ->whereIn('action', ['warn', 'remove', 'mute', 'ban'])
            ->latest('id')
            ->get()
            ->groupBy('target_user_id')
            ->map->first();

        return $users->map(function (User $u) use ($last) {
            $action = $last->get($u->id);
            $banned = $u->banned_at !== null;

            return [
                'ref' => $this->accountRef($u->id),
                'status' => $banned ? 'Banned' : 'Muted',
                'since' => $banned ? $u->banned_at : null,
                'until' => $banned ? null : $u->muted_until,
                'warnings' => (int) $u->warnings_count,
                'last_action' => $action?->action,
                'last_reason' => $action?->reason,
                'last_by' => $action?->moderator?->name,
                'report_id' => $action?->report_id,
            ];
        });
    }

    /* ---------- audit log ---------- */

    public function activity(): Paginator
    {
        return ModAction::query()
            ->with(['moderator:id,name,role', 'report:id,category'])
            ->latest('id')
            ->simplePaginate(25)
            ->through(fn (ModAction $a) => [
                'action' => $a->action,
                'label' => self::ACTION_LABELS[$a->action] ?? $a->action,
                'reason' => $a->reason,
                'by' => $a->moderator?->name ?? 'Removed account',
                'role' => $a->moderator?->role,
                'category' => $a->report?->category,
                'report_id' => $a->report_id,
                'account' => $a->target_user_id ? $this->accountRef($a->target_user_id) : null,
                'at' => $a->created_at,
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function recentActivity(int $limit = 5): Collection
    {
        return ModAction::query()
            ->with('moderator:id,name,role')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (ModAction $a) => [
                'label' => self::ACTION_LABELS[$a->action] ?? $a->action,
                'by' => $a->moderator?->name ?? 'Removed account',
                'report_id' => $a->report_id,
                'at' => $a->created_at,
            ]);
    }

    public static function when(?\Carbon\CarbonInterface $time, string $format = 'M j, g:i A'): string
    {
        return $time === null ? '' : $time->copy()->timezone(self::TIMEZONE)->format($format);
    }

    public function accountRef(int $userId): string
    {
        return 'Account '.str_pad((string) $userId, 4, '0', STR_PAD_LEFT);
    }

    /* ---------- internals ---------- */

    private function baseQuery(): Builder
    {
        return Report::query()
            ->with('reportable')
            ->withCount([
                'votes',
                'votes as agree_count' => fn ($q) => $q->where('choice', 'agree'),
                'votes as false_count' => fn ($q) => $q->where('choice', 'false_report'),
                'votes as wrong_tag_count' => fn ($q) => $q->where('choice', 'wrong_tag'),
            ]);
    }

    private function view(Builder $query, string $view): Builder
    {
        return match ($view) {
            'referred' => $query->whereNotNull('referred_guidance_at')->whereNull('guidance_handled_at'),
            'voting' => $query->whereIn('status', [Report::OPEN, Report::VOTING])->whereNull('mod_status'),
            'closed' => $query->where(fn ($q) => $q->where('mod_status', 'reviewed')
                ->orWhere(fn ($q) => $q->where('status', Report::RESOLVED)->whereNull('mod_status'))),
            'all' => $query,
            default => $query->where('mod_status', 'pending'),
        };
    }

    private function orderBySeverity(Builder $query): void
    {
        $marks = fn (array $list) => implode(',', array_fill(0, count($list), '?'));

        $query->orderByRaw(
            'CASE WHEN category IN ('.$marks(Report::CRITICAL).') THEN 1
                  WHEN category IN ('.$marks(Report::HIGH).') THEN 2
                  WHEN category IN ('.$marks(Report::MEDIUM).') THEN 3
                  ELSE 4 END',
            [...Report::CRITICAL, ...Report::HIGH, ...Report::MEDIUM],
        );
    }

    /** @return array<string, mixed> */
    private function row(Report $r): array
    {
        $target = $r->reportable;

        return [
            'id' => $r->id,
            'severity' => Report::severityFor($r->category),
            'category' => $r->category,
            'kind' => $target instanceof Post ? 'post' : 'comment',
            'excerpt' => Str::limit((string) ($target?->body ?? '(this content no longer exists)'), 150),
            'removed' => $target?->removed_at !== null,
            'about_reporter' => $r->mod_subject === 'reporter',
            'stage' => $this->stage($r),
            'votes' => (int) $r->votes_count,
            'agree' => (int) $r->agree_count,
            'referred' => $r->referred_guidance_at !== null && $r->guidance_handled_at === null,
            'age' => $r->created_at?->diffForHumans(null, \Carbon\CarbonInterface::DIFF_ABSOLUTE, true),
        ];
    }

    private function stage(Report $r): string
    {
        return match (true) {
            $r->mod_status === 'pending' => 'Needs a decision',
            $r->mod_status === 'reviewed' => 'Decided',
            $r->status === Report::RESOLVED => 'Closed by vote',
            default => 'Community is voting',
        };
    }

    /** The name other students saw next to this post or comment. */
    private function label(Post|Comment|null $target): string
    {
        if ($target === null) {
            return 'Unknown';
        }

        $postId = $target instanceof Comment ? $target->post_id : $target->id;
        $name = $target->author?->display_name;

        if (! $target->is_anonymous && $name !== null && trim($name) !== '') {
            return $name;
        }

        return Alias::for((int) $target->author_id, (int) $postId);
    }

    /** @return array<string, int> earlier warn / mute / ban actions on this account (not counting this case) */
    private function priorActions(?User $subject, int $exceptReportId): array
    {
        if ($subject === null) {
            return [];
        }

        return ModAction::query()
            ->where('target_user_id', $subject->id)
            ->whereIn('action', ['warn', 'mute', 'ban'])
            ->where(fn ($q) => $q->whereNull('report_id')->orWhere('report_id', '!=', $exceptReportId))
            ->selectRaw('action, count(*) as total')
            ->groupBy('action')
            ->pluck('total', 'action')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /** What staff may know about the account without knowing who it is. @return array<string, mixed> */
    private function record(?User $subject, array $prior): array
    {
        $muted = $subject?->muted_until !== null && Carbon::parse($subject->muted_until)->isFuture();
        $parts = [];

        foreach (['warn' => 'warning', 'mute' => 'mute', 'ban' => 'ban'] as $key => $word) {
            if (($prior[$key] ?? 0) > 0) {
                $n = $prior[$key];
                $parts[] = $n.' '.$word.($n === 1 ? '' : 's');
            }
        }

        return [
            'ref' => $subject ? $this->accountRef($subject->id) : null,
            'total' => array_sum($prior),
            'summary' => $parts === [] ? 'No earlier warnings, mutes or bans on this account.' : 'Earlier on this account: '.implode(', ', $parts).'.',
            'muted_until' => $muted ? Carbon::parse($subject->muted_until) : null,
            'banned' => $subject?->banned_at !== null,
        ];
    }

    /**
     * Advice, not a rule: staff always choose. Escalates warn -> mute -> ban for repeat offences, and treats
     * self-harm as a student who needs support rather than someone to punish.
     *
     * @return array{action: string, why: string}|null
     */
    private function suggest(Report $report, ?User $subject, array $prior): ?array
    {
        if ($subject === null || $subject->banned_at !== null) {
            return null;
        }

        if ($report->mod_subject !== 'reporter' && $report->category === 'Self-harm') {
            return ['action' => 'refer', 'why' => 'This may be a student who needs support. Reach out through Guidance before thinking about a penalty.'];
        }

        if ($report->mod_subject !== 'reporter' && $report->category === 'Threats') {
            return ['action' => 'remove', 'why' => 'Remove the content first, then decide on the account. Refer to Guidance if the threat looks real.'];
        }

        $offences = array_sum($prior);

        return match (true) {
            $offences === 0 => ['action' => 'warn', 'why' => 'First recorded offence on this account.'],
            $offences === 1 => ['action' => 'mute', 'why' => 'Second offence. A mute stops new posts while the student is spoken to.'],
            default => ['action' => 'ban', 'why' => 'Third or later offence. Earlier warnings and mutes did not change the behaviour.'],
        };
    }

    /** @return Collection<int, array<string, mixed>> */
    private function timeline(int $reportId): Collection
    {
        return ModAction::query()
            ->with('moderator:id,name,role')
            ->where('report_id', $reportId)
            ->oldest('id')
            ->get()
            ->map(fn (ModAction $a) => [
                'label' => self::ACTION_LABELS[$a->action] ?? $a->action,
                'by' => $a->moderator?->name ?? 'Removed account',
                'role' => $a->moderator?->role,
                'reason' => $a->reason,
                'at' => $a->created_at,
            ]);
    }

    /**
     * The identity this staff member revealed on this case, if they did so recently. Nobody else sees it:
     * each person has to reveal (and explain why) for themselves.
     *
     * @return array<string, mixed>|null
     */
    private function revealedTo(User $viewer, Report $report, ?User $subject): ?array
    {
        if ($subject === null) {
            return null;
        }

        $reveal = ModAction::query()
            ->where('report_id', $report->id)
            ->where('moderator_id', $viewer->id)
            ->where('target_user_id', $subject->id)
            ->where('action', 'unmask')
            ->where('created_at', '>=', now()->subMinutes(self::REVEAL_WINDOW_MINUTES))
            ->latest('id')
            ->first();

        if ($reveal === null) {
            return null;
        }

        return [
            'name' => (string) ($subject->display_name ?: $subject->name),
            'email' => (string) $subject->email,
            'student_id' => $subject->student_id,
            'until' => $reveal->created_at->copy()->addMinutes(self::REVEAL_WINDOW_MINUTES),
        ];
    }

    private function sanctionedUsers(): Builder
    {
        return User::query()->where(fn ($q) => $q->whereNotNull('banned_at')->orWhere('muted_until', '>', now()));
    }
}
