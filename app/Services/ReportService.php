<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Block;
use App\Models\Post;
use App\Models\Report;
use App\Models\ReportVote;
use App\Support\VoteTally;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Standard reports enter community voting; critical safety reports go directly
 * to moderator review and never enter the public vote flow.
 *
 * Voters pick: Agree | False report | Wrong tag. The vote closes at QUORUM votes, or after
 * VOTING_HOURS if at least MIN_VOTES voted. Plurality wins; a tie or too few votes = no consensus.
 *
 *  upheld        removed. Author loses points, reporter earns points. If GUIDANCE_AGREE_VOTES or more
 *                voters agreed, the case is also referred to the Guidance account.
 *  wrong_tag     removed too (there IS a violation, just not the one reported). Reporter loses a few
 *                points. Goes to the moderator queue.
 *  false_report  glow removed, post/comment stays as if nothing happened. Reporter loses points and
 *                is sent to the moderator queue to be reviewed.
 *  no_consensus  glow removed, nobody is punished.
 */
class ReportService
{
    public const QUORUM = 5;

    public const MIN_VOTES = 3;

    public const VOTING_HOURS = 24;

    /** This many "Agree" votes (or more) = "many valid votes" -> referred to Guidance. */
    public const GUIDANCE_AGREE_VOTES = 4;

    public const TYPES = ['post' => Post::class, 'comment' => Comment::class];

    public function __construct(private PointService $points, private AlertService $alerts, private ImageService $images)
    {
    }

    public function report(int $reporterId, string $type, int $id, string $category, ?string $details = null): Report
    {
        $class = self::TYPES[$type] ?? null;

        if ($class === null || ! in_array($category, Report::CATEGORIES, true)) {
            throw $this->error('That report is not valid.');
        }

        $target = $class::find($id);

        if ($target === null || $target->removed_at !== null) {
            throw $this->error('That post or comment no longer exists.');
        }

        if ((int) $target->author_id === $reporterId) {
            throw $this->error('You cannot report your own post or comment.');
        }

        if (Block::existsBetween($reporterId, (int) $target->author_id)) {
            throw $this->error('You cannot interact with this user.');
        }

        $alreadyActive = Report::where('reportable_type', $class)
            ->where('reportable_id', $id)
            ->where('status', '!=', Report::RESOLVED)
            ->exists();

        if ($alreadyActive) {
            throw $this->error('This has already been reported.');
        }

        $critical = in_array($category, Report::CRITICAL, true);

        try {
            $report = Report::create([
                'reporter_id' => $reporterId,
                'reportable_type' => $class,
                'reportable_id' => $id,
                'category' => $category,
                'details' => $details !== null && trim($details) !== '' ? trim($details) : null,
                'status' => $critical ? Report::ESCALATED : Report::OPEN,
                'mod_status' => $critical ? 'pending' : null,
                'mod_subject' => $critical ? 'content' : null,
            ]);
        } catch (QueryException $e) {
            throw $this->error('You already reported this.');
        }

        if ($critical) {
            $this->alerts->notifyRoles(
                ['moderator', 'admin'],
                'notice',
                'Urgent report needs review',
                "A {$category} report needs moderator review.",
                route('moderation')
            );
            $this->alerts->notify(
                $reporterId,
                'report',
                'Your report is under moderator review',
                'A moderator will review this report directly. It will not be sent to a community vote.'
            );
        }

        return $report;
    }

    public function startVoting(int $userId, int $reportId): void
    {
        DB::transaction(function () use ($userId, $reportId) {
            $report = Report::lockForUpdate()->findOrFail($reportId);

            if (in_array($report->category, Report::CRITICAL, true)) {
                throw $this->error('Safety reports are handled by moderators and cannot be voted on.');
            }

            if ($report->status !== Report::OPEN) {
                throw $this->error('Voting has already started for this report.');
            }

            $this->assertMayParticipate($userId, $report);

            $report->update([
                'status' => Report::VOTING,
                'voting_started_at' => now(),
                'voting_ends_at' => now()->addHours(self::VOTING_HOURS),
            ]);
        });
    }

    public function vote(int $userId, int $reportId, string $choice): void
    {
        if (! in_array($choice, VoteTally::CHOICES, true)) {
            throw $this->error('Pick one of the three options.');
        }

        DB::transaction(function () use ($userId, $reportId, $choice) {
            $report = Report::lockForUpdate()->findOrFail($reportId);

            if (in_array($report->category, Report::CRITICAL, true)) {
                throw $this->error('Safety reports are handled by moderators and cannot be voted on.');
            }

            if ($report->status !== Report::VOTING) {
                throw $this->error('Voting is not open for this report.');
            }

            // Window already over: close it with the votes we have instead of accepting a late one.
            if ($report->voting_ends_at !== null && $report->voting_ends_at->isPast()) {
                $this->resolve($report);

                return;
            }

            $this->assertMayParticipate($userId, $report);

            try {
                ReportVote::create(['report_id' => $report->id, 'voter_id' => $userId, 'choice' => $choice]);
            } catch (QueryException $e) {
                throw $this->error('You already voted on this report.');
            }

            if ($report->votes()->count() >= self::QUORUM) {
                $this->resolve($report);
            }
        });
    }

    /** Close every vote whose time is up. Called on feed/post load and by `reports:resolve-due`. */
    public function sweepDue(): int
    {
        $ids = Report::where('status', Report::VOTING)
            ->whereNotIn('category', Report::CRITICAL)
            ->where('voting_ends_at', '<=', now())
            ->pluck('id');

        foreach ($ids as $id) {
            DB::transaction(function () use ($id) {
                $report = Report::lockForUpdate()->find($id);

                if ($report !== null && $report->status === Report::VOTING) {
                    $this->resolve($report);
                }
            });
        }

        return $ids->count();
    }

    /**
     * Active (open / voting) report for a list of posts or comments, keyed by target id.
     *
     * @param  Collection<int, Model>  $targets
     * @return array<int, array<string, mixed>>
     */
    public function statesFor(int $viewerId, string $class, Collection $targets): array
    {
        if ($targets->isEmpty()) {
            return [];
        }

        $authors = $targets->pluck('author_id', 'id');

        $reports = Report::query()
            ->where('reportable_type', $class)
            ->whereIn('reportable_id', $targets->pluck('id'))
            ->whereIn('status', [Report::OPEN, Report::VOTING])
            ->withCount('votes')
            ->withExists(['votes as has_voted' => fn ($q) => $q->where('voter_id', $viewerId)])
            ->get()
            ->keyBy('reportable_id');

        $states = [];

        foreach ($reports as $targetId => $report) {
            $isAuthor = (int) $authors[$targetId] === $viewerId;
            $isReporter = (int) $report->reporter_id === $viewerId;
            $canParticipate = ! $isAuthor && ! $isReporter;

            $states[$targetId] = [
                'id' => $report->id,
                'status' => $report->status,
                'category' => $report->category,
                'votes' => (int) $report->votes_count,
                'quorum' => self::QUORUM,
                'ends_in' => $report->voting_ends_at?->diffForHumans(null, \Carbon\CarbonInterface::DIFF_ABSOLUTE, true),
                'is_reporter' => $isReporter,
                'is_author' => $isAuthor,
                'has_voted' => (bool) $report->has_voted,
                'can_start' => $report->status === Report::OPEN && $canParticipate,
                'can_vote' => $report->status === Report::VOTING && $canParticipate && ! $report->has_voted,
            ];
        }

        return $states;
    }

    /* ---------- internals ---------- */

    /** Must be called inside a transaction with the report row locked. */
    private function resolve(Report $report): void
    {
        if ($report->status !== Report::VOTING
            || $report->outcome === 'moderator_cleared'
            || in_array($report->category, Report::CRITICAL, true)) {
            return;
        }

        $counts = $report->votes()->selectRaw('choice, count(*) as total')->groupBy('choice')->pluck('total', 'choice')->all();
        $outcome = VoteTally::decide($counts, self::MIN_VOTES);

        $target = $report->reportable;
        $authorId = $target?->author_id;
        $kind = $target instanceof Post ? 'post' : 'comment';
        $reviewed = $report->mod_status === 'reviewed';

        $changes = ['status' => Report::RESOLVED, 'outcome' => $outcome, 'resolved_at' => now()];

        if ($outcome === VoteTally::UPHELD || $outcome === VoteTally::WRONG_TAG) {
            if ($target !== null && $target->removed_at === null) {
                $target->forceFill(['removed_at' => now(), 'removed_by' => 'community'])->save();

                if ($target instanceof Post) {
                    $this->images->deletePostImages($target);
                }
            }

            if ($authorId !== null) {
                $this->points->award($authorId, PointService::REPORT_UPHELD_AUTHOR, 'report_upheld_author', $report);
                $this->alerts->notify($authorId, 'notice', 'Notice from moderators', "Your {$kind} was removed after a community vote ({$report->category}). {$this->pointsText(PointService::REPORT_UPHELD_AUTHOR)}");
            }

            if ($outcome === VoteTally::UPHELD) {
                $this->points->award($report->reporter_id, PointService::REPORT_UPHELD_REPORTER, 'report_upheld_reporter', $report);
                $this->alerts->notify($report->reporter_id, 'report', 'Report update', "Your report was reviewed: the community agreed. {$this->pointsText(PointService::REPORT_UPHELD_REPORTER)}");
            } else {
                $this->points->award($report->reporter_id, PointService::WRONG_TAG, 'wrong_tag', $report);
                $this->alerts->notify($report->reporter_id, 'report', 'Report update', "Your report was reviewed: the {$kind} was removed, but the community said the tag ({$report->category}) was wrong. {$this->pointsText(PointService::WRONG_TAG)}");
            }

            if (! $reviewed) {
                $changes['mod_status'] = 'pending';
                $changes['mod_subject'] = 'content';
            }

            if ($outcome === VoteTally::UPHELD && (int) ($counts[VoteTally::AGREE] ?? 0) >= self::GUIDANCE_AGREE_VOTES && $report->referred_guidance_at === null) {
                $changes['referred_guidance_at'] = now();
                $this->alerts->notifyRoles(['guidance', 'admin'], 'notice', 'Referred case', "A {$kind} reported for {$report->category} was removed with strong community agreement.", route('moderation', ['tab' => 'guidance']));
            }
        } elseif ($outcome === VoteTally::FALSE_REPORT) {
            $this->points->award($report->reporter_id, PointService::FALSE_REPORT, 'false_report', $report);
            $this->alerts->notify($report->reporter_id, 'report', 'Report update', "Your report was reviewed: the community judged it a false report, so the {$kind} stays. {$this->pointsText(PointService::FALSE_REPORT)}");

            if (! $reviewed) {
                $changes['mod_status'] = 'pending';
                $changes['mod_subject'] = 'reporter';
            }
        } else {
            $this->alerts->notify($report->reporter_id, 'report', 'Report update', "Your report was reviewed: the community did not reach a clear result, so the {$kind} stays.");
        }

        $report->update($changes);
    }

    private function pointsText(int $amount): string
    {
        return 'Points: '.($amount > 0 ? '+' : '').$amount.'.';
    }

    private function assertMayParticipate(int $userId, Report $report): void
    {
        $authorId = (int) $report->reportable?->author_id;

        if ($userId === $authorId) {
            throw $this->error('You cannot do this on your own post or comment.');
        }

        if ($userId === (int) $report->reporter_id) {
            throw $this->error('You filed this report, so you cannot start or vote on it.');
        }
    }

    private function error(string $message): ValidationException
    {
        return ValidationException::withMessages(['report' => $message]);
    }
}
