<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Block;
use App\Models\Bookmark;
use App\Models\Comment;
use App\Models\OtpCode;
use App\Models\PointLog;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountDeletionService
{
    public const TOMBSTONE_NAME = 'Deleted User';

    public const TOMBSTONE_BODY = '[deleted]';

    public const EXCERPT_LENGTH = 120;

    public function __construct(private ImageService $images, private AlertService $alerts)
    {
    }

    public function delete(User $user): void
    {
        $files = DB::transaction(function () use ($user) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($locked->deleted_at !== null) {
                return ['avatar' => null, 'photos' => []];
            }

            if ($locked->banned_at !== null) {
                throw $this->error('This account is suspended, so it cannot be deleted. Please appeal to a moderator first.');
            }

            if (in_array($locked->role, [...ModerationService::STAFF, 'dpo'], true)) {
                throw $this->error('Moderator, Guidance and admin accounts cannot be deleted here. Ask an admin to remove your role first.');
            }

            $id = (int) $locked->id;
            $oldEmail = (string) $locked->email;
            $avatar = $locked->avatar_path;
            $postIds = Post::where('author_id', $id)->pluck('id')->all();
            $commentIds = Comment::where('author_id', $id)->pluck('id')->all();

            $this->warnAboutOpenSafetyCases($postIds, $commentIds);

            $photos = PostImage::whereIn('post_id', $postIds)->pluck('path')->all();
            PostImage::whereIn('post_id', $postIds)->delete();

            $this->onTheirContent(
                Report::query()
                    ->where('status', '!=', Report::RESOLVED)
                    ->whereNotIn('category', Report::CRITICAL),
                $postIds,
                $commentIds,
            )->update([
                'status' => Report::RESOLVED,
                'outcome' => 'author_deleted',
                'resolved_at' => now(),
                'voting_ends_at' => now(),
            ]);

            $now = now();
            Post::where('author_id', $id)->whereNull('removed_at')->update(['removed_at' => $now, 'removed_by' => 'author']);
            Post::where('author_id', $id)->update([
                'body' => self::TOMBSTONE_BODY,
                'price' => null,
                'condition' => null,
            ]);
            DB::table('post_items')->whereIn('post_id', $postIds)->update(['label' => null]);

            Comment::where('author_id', $id)->whereNull('removed_at')->update(['removed_at' => $now, 'removed_by' => 'author']);
            Comment::where('author_id', $id)->update(['body' => self::TOMBSTONE_BODY]);

            Report::where('reporter_id', $id)->update(['details' => null]);

            Alert::where('user_id', $id)->delete();
            Bookmark::where('user_id', $id)->delete();
            Block::where('blocker_id', $id)->orWhere('blocked_id', $id)->delete();
            PointLog::where('user_id', $id)->delete();
            OtpCode::where('email', $oldEmail)->delete();
            $locked->tokens()->delete();

            User::whereKey($id)->update([
                'name' => self::TOMBSTONE_NAME,
                'email' => "deleted-{$id}@deleted.invalid",
                'display_name' => null,
                'student_id' => null,
                'avatar_path' => null,
                'email_verified_at' => null,
                'verified_at' => null,
                'password' => null,
                'remember_token' => null,
                'role' => 'student',
                'muted_until' => null,
                'points' => 0,
                'accepted_terms_at' => null,
                'terms_version' => null,
                'deleted_at' => $now,
            ]);

            return ['avatar' => $avatar, 'photos' => $photos];
        });

        $this->images->deletePaths($files['photos']);

        try {
            $this->images->deleteAvatar($files['avatar']);
        } catch (\RuntimeException $e) {
            report($e);
        }
    }

    /**
     * @param  array<int, int>  $postIds
     * @param  array<int, int>  $commentIds
     */
    private function warnAboutOpenSafetyCases(array $postIds, array $commentIds): void
    {
        $cases = $this->onTheirContent(
            Report::query()
                ->whereIn('category', Report::CRITICAL)
                ->where(fn ($q) => $q->where('status', '!=', Report::RESOLVED)->orWhere('mod_status', 'pending')),
            $postIds,
            $commentIds,
        )->with('reportable')->get();

        foreach ($cases as $case) {
            $target = $case->reportable;

            if ($target === null) {
                continue;
            }

            $kind = $target instanceof Post ? 'post' : 'comment';
            $this->alerts->notifyRoles(
                ['moderator', 'admin'],
                'notice',
                'Safety case: author deleted account',
                "The author of a {$kind} reported for {$case->category} deleted their account. Their content is erased now and their identity can no longer be unmasked. Last text: \"".Str::limit((string) $target->body, self::EXCERPT_LENGTH).'"',
                route('moderation'),
            );
        }
    }

    /**
     * @param  Builder<Report>  $query
     * @param  array<int, int>  $postIds
     * @param  array<int, int>  $commentIds
     * @return Builder<Report>
     */
    private function onTheirContent(Builder $query, array $postIds, array $commentIds): Builder
    {
        return $query->where(function ($q) use ($postIds, $commentIds) {
            $q->where(fn ($w) => $w->where('reportable_type', Post::class)->whereIn('reportable_id', $postIds))
                ->orWhere(fn ($w) => $w->where('reportable_type', Comment::class)->whereIn('reportable_id', $commentIds));
        });
    }

    private function error(string $message): ValidationException
    {
        return ValidationException::withMessages(['report' => $message]);
    }
}
