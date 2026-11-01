<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\ModAction;
use App\Models\Post;
use App\Models\Report;
use App\Models\ReportVote;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Pinned reports, the three vote outcomes, moderator queue, guidance, mute/ban,
 * alerts, accepted answers, search, profile and photos.
 */
class ModerationFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(User $author, string $body = 'a normal post'): Post
    {
        return Post::create(['author_id' => $author->id, 'category' => 'Ask', 'body' => $body, 'is_anonymous' => true]);
    }

    /** Report as $reporter, start the vote, then cast the given choices with fresh students. */
    private function reportAndVote(Post $post, User $reporter, string $category, array $choices): Report
    {
        $this->actingAs($reporter)->post("/report/post/{$post->id}", ['category' => $category]);
        $report = Report::latest('id')->firstOrFail();

        $voters = User::factory()->count(count($choices))->create();
        $this->actingAs($voters[0])->post("/reports/{$report->id}/start");

        foreach ($choices as $i => $choice) {
            $this->actingAs($voters[$i])->post("/reports/{$report->id}/vote", ['choice' => $choice]);
        }

        return $report->fresh();
    }

    public function test_reported_post_and_comment_are_pinned_to_the_top_for_other_students(): void
    {
        [$author, $reporter, $viewer] = User::factory()->count(3)->create();
        $old = $this->makePost($author, 'OLD-REPORTED-POST');
        $this->makePost($author, 'NEWER-NORMAL-POST');
        $comment = Comment::create(['post_id' => $old->id, 'author_id' => $author->id, 'body' => 'REPORTED-COMMENT-TEXT', 'is_anonymous' => true]);

        $this->actingAs($reporter)->post("/report/post/{$old->id}", ['category' => 'Spam']);
        $this->actingAs($reporter)->post("/report/comment/{$comment->id}", ['category' => 'Harassment']);

        $this->actingAs($viewer)->get('/feed')->assertOk()
            ->assertSeeInOrder(['Reported · needs your vote', 'OLD-REPORTED-POST', 'NEWER-NORMAL-POST'])
            ->assertSee('REPORTED-COMMENT-TEXT')
            ->assertSee('cm-reported');
    }

    public function test_reporters_do_not_see_their_reported_posts_and_authors_do_not_see_the_banner(): void
    {
        [$author, $reporter, $viewer] = User::factory()->count(3)->create();
        $post = $this->makePost($author, 'ONE-COPY-OF-REPORTED-POST');
        $comment = Comment::create([
            'post_id' => $post->id,
            'author_id' => $author->id,
            'body' => 'AUTHOR-REPORTED-COMMENT',
            'is_anonymous' => true,
        ]);

        Report::create([
            'reporter_id' => $reporter->id,
            'reportable_type' => Post::class,
            'reportable_id' => $post->id,
            'category' => 'Spam',
            'status' => Report::OPEN,
        ]);
        Report::create([
            'reporter_id' => $reporter->id,
            'reportable_type' => Comment::class,
            'reportable_id' => $comment->id,
            'category' => 'Spam',
            'status' => Report::OPEN,
        ]);

        $reporterResponse = $this->actingAs($reporter)->get('/feed')->assertOk()
            ->assertDontSee('ONE-COPY-OF-REPORTED-POST')
            ->assertDontSee('cm-report-banner');

        $authorResponse = $this->actingAs($author)->get('/feed')->assertOk()
            ->assertSee('ONE-COPY-OF-REPORTED-POST')
            ->assertDontSee('Reported · needs your vote')
            ->assertDontSee('cm-report-banner');
        $threadResponse = $this->actingAs($author)->get("/post/{$post->id}")
            ->assertOk()
            ->assertSee('ONE-COPY-OF-REPORTED-POST')
            ->assertSee('AUTHOR-REPORTED-COMMENT')
            ->assertDontSee('cm-report-banner');
        preg_match('/<div class="cm-comment[^"]*"[^>]*id="comment-'.$comment->id.'"/', $threadResponse->getContent(), $commentWrapper);
        $this->assertNotEmpty($commentWrapper);
        $this->assertStringNotContainsString('cm-reported', $commentWrapper[0]);

        $viewerResponse = $this->actingAs($viewer)->get('/feed')->assertOk()
            ->assertSee('ONE-COPY-OF-REPORTED-POST')
            ->assertSee('cm-report-banner');

        $this->assertSame(0, substr_count($reporterResponse->getContent(), 'id="post-'.$post->id.'"'));
        $this->assertSame(1, substr_count($authorResponse->getContent(), 'id="post-'.$post->id.'"'));
        $this->assertSame(1, substr_count($viewerResponse->getContent(), 'id="post-'.$post->id.'"'));
    }

    public function test_multiple_active_reports_do_not_duplicate_a_post_in_the_feed(): void
    {
        [$author, $reporterOne, $reporterTwo, $viewer] = User::factory()->count(4)->create();
        $post = $this->makePost($author, 'MULTI-REPORTED-POST');

        foreach ([$reporterOne, $reporterTwo] as $reporter) {
            Report::create([
                'reporter_id' => $reporter->id,
                'reportable_type' => Post::class,
                'reportable_id' => $post->id,
                'category' => 'Spam',
                'status' => Report::OPEN,
            ]);
        }

        $response = $this->actingAs($viewer)->get('/feed')
            ->assertOk()
            ->assertSee('MULTI-REPORTED-POST')
            ->assertSee('cm-report-banner');

        $this->assertSame(1, substr_count($response->getContent(), 'id="post-'.$post->id.'"'));
    }

    public function test_agree_removes_content_and_refers_to_guidance_when_many_agree(): void
    {
        $author = User::factory()->create(['points' => 20]);
        $reporter = User::factory()->create();
        $guidance = User::factory()->create(['role' => 'guidance']);
        $post = $this->makePost($author, 'BAD-POST');

        $report = $this->reportAndVote($post, $reporter, 'Harassment', ['agree', 'agree', 'agree', 'agree', 'false_report']);

        $this->assertSame('upheld', $report->outcome);
        $this->assertNotNull($post->fresh()->removed_at);
        $this->assertNotNull($report->referred_guidance_at);
        $this->assertSame(10, $author->fresh()->points);
        $this->assertSame(3, $reporter->fresh()->points);
        $this->assertTrue(Alert::where('user_id', $author->id)->where('title', 'Notice from moderators')->exists());
        $this->assertTrue(Alert::where('user_id', $guidance->id)->where('title', 'Referred case')->exists());

        // Removed post is gone from the feed.
        $this->actingAs($reporter)->get('/feed')->assertDontSee('BAD-POST');
        // Guidance sees it, a student does not get in.
        $this->actingAs($guidance)->get('/moderation')->assertOk()->assertSee('BAD-POST');
        $this->actingAs($reporter)->get('/moderation')->assertForbidden();
    }

    public function test_agree_without_many_votes_is_not_referred_to_guidance(): void
    {
        $author = User::factory()->create();
        $reporter = User::factory()->create();
        $post = $this->makePost($author);

        $report = $this->reportAndVote($post, $reporter, 'Spam', ['agree', 'agree', 'agree', 'false_report', 'wrong_tag']);

        $this->assertSame('upheld', $report->outcome);
        $this->assertNull($report->referred_guidance_at);
        $this->assertNotNull($post->fresh()->removed_at);
    }

    public function test_community_and_moderator_removals_delete_post_photos(): void
    {
        $uploadDirectory = public_path('uploads/posts');
        if (! is_dir($uploadDirectory)) {
            mkdir($uploadDirectory, 0775, true);
        }

        $communityAuthor = User::factory()->create();
        $communityReporter = User::factory()->create();
        $communityPhoto = 'uploads/posts/'.\Illuminate\Support\Str::uuid().'.jpg';
        file_put_contents(public_path($communityPhoto), 'community photo');
        $communityPost = $this->makePost($communityAuthor, 'COMMUNITY-PHOTO-POST');
        $communityPost->update(['image_path' => $communityPhoto]);

        $this->reportAndVote($communityPost, $communityReporter, 'Harassment', [
            'agree', 'agree', 'agree', 'agree', 'false_report',
        ]);

        $this->assertNotNull($communityPost->fresh()->removed_at);
        $this->assertFileDoesNotExist(public_path($communityPhoto));

        $moderator = User::factory()->create(['role' => 'moderator']);
        $moderatorAuthor = User::factory()->create();
        $moderatorReporter = User::factory()->create();
        $moderatorPhoto = 'uploads/posts/'.\Illuminate\Support\Str::uuid().'.jpg';
        file_put_contents(public_path($moderatorPhoto), 'moderator photo');
        $moderatorPost = $this->makePost($moderatorAuthor, 'MODERATOR-PHOTO-POST');
        $moderatorPost->update(['image_path' => $moderatorPhoto]);
        $this->actingAs($moderatorReporter)->post("/report/post/{$moderatorPost->id}", ['category' => 'Threats']);
        $moderatorReport = Report::latest('id')->firstOrFail();

        $this->actingAs($moderator)
            ->post("/moderation/{$moderatorReport->id}", ['action' => 'remove', 'reason' => 'Remove harmful content'])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($moderatorPost->fresh()->removed_at);
        $this->assertFileDoesNotExist(public_path($moderatorPhoto));
    }

    public function test_false_report_keeps_post_clears_red_and_sends_reporter_to_moderators(): void
    {
        $author = User::factory()->create();
        $reporter = User::factory()->create([
            'points' => 10,
            'name' => 'Rita Legal Name',
            'display_name' => 'Rita Reporter',
        ]);
        $mod = User::factory()->create(['role' => 'moderator']);
        $post = $this->makePost($author, 'FINE-POST');

        $report = $this->reportAndVote($post, $reporter, 'Spam', ['false_report', 'false_report', 'false_report', 'agree', 'wrong_tag']);

        $this->assertSame('false_report', $report->outcome);
        $this->assertNull($post->fresh()->removed_at);
        $this->assertSame(5, $reporter->fresh()->points);
        $this->assertSame('pending', $report->mod_status);
        $this->assertSame('reporter', $report->mod_subject);

        // Back to normal: no red glow, still in the feed.
        $this->actingAs($author)->get('/feed')->assertSee('FINE-POST')->assertDontSee('cm-reported');

        // Moderator sees the reporter, must give a reason, then can warn them.
        $this->actingAs($mod)->get('/moderation')->assertOk()
            ->assertSee('Rita Reporter')
            ->assertSee('False report')
            ->assertDontSee('Rita Legal Name')
            ->assertDontSee($reporter->email);
        $this->actingAs($mod)->post("/moderation/{$report->id}", ['action' => 'warn', 'reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($mod)->post("/moderation/{$report->id}", ['action' => 'warn', 'reason' => 'Do not file false reports.'])->assertSessionHasNoErrors();

        $this->assertSame(1, (int) User::find($reporter->id)->warnings_count);
        $this->assertSame('reviewed', $report->fresh()->mod_status);
        $this->assertTrue(ModAction::where('report_id', $report->id)->where('action', 'warn')->where('reason', 'Do not file false reports.')->exists());
        $this->assertTrue(Alert::where('user_id', $reporter->id)->where('body', 'like', 'Warning:%')->exists());
    }

    public function test_wrong_tag_still_removes_the_post_and_costs_the_reporter_a_little(): void
    {
        $author = User::factory()->create(['points' => 20]);
        $reporter = User::factory()->create(['points' => 10]);
        $post = $this->makePost($author, 'MISINFO-POST');

        $report = $this->reportAndVote($post, $reporter, 'Self-harm', ['wrong_tag', 'wrong_tag', 'wrong_tag', 'agree', 'false_report']);

        $this->assertSame('wrong_tag', $report->outcome);
        $this->assertNotNull($post->fresh()->removed_at);
        $this->assertSame(8, $reporter->fresh()->points);
        $this->assertSame(10, $author->fresh()->points);
        $this->assertSame('pending', $report->mod_status);
    }

    public function test_no_consensus_clears_the_red_without_punishing_anyone(): void
    {
        $author = User::factory()->create(['points' => 10]);
        $reporter = User::factory()->create(['points' => 10]);
        $post = $this->makePost($author);

        $report = $this->reportAndVote($post, $reporter, 'Spam', ['agree', 'agree', 'false_report', 'false_report', 'wrong_tag']);

        $this->assertSame('no_consensus', $report->outcome);
        $this->assertNull($post->fresh()->removed_at);
        $this->assertSame(10, $author->fresh()->points);
        $this->assertSame(10, $reporter->fresh()->points);
    }

    public function test_critical_report_reaches_moderators_before_the_vote(): void
    {
        $author = User::factory()->create();
        $reporter = User::factory()->create();
        $mod = User::factory()->create(['role' => 'moderator']);
        $post = $this->makePost($author, 'WORRYING-POST');

        $this->actingAs($reporter)->post("/report/post/{$post->id}", ['category' => 'Self-harm', 'details' => 'sounds serious']);

        $this->actingAs($mod)->get('/moderation')->assertOk()->assertSee('WORRYING-POST')->assertSee('Critical')->assertSee('sounds serious')->assertSee('Refer to Guidance');
        $this->actingAs($mod)->get('/moderation?severity=medium')->assertDontSee('WORRYING-POST');
    }

    public function test_critical_reports_are_ordered_before_the_queue_limit(): void
    {
        $author = User::factory()->create();
        $post = $this->makePost($author);

        foreach (User::factory()->count(100)->create() as $reporter) {
            Report::create([
                'reporter_id' => $reporter->id,
                'reportable_type' => Post::class,
                'reportable_id' => $post->id,
                'category' => 'Other',
                'status' => 'open',
                'mod_status' => 'pending',
                'mod_subject' => 'content',
            ]);
        }

        $criticalReporter = User::factory()->create();
        $criticalReport = Report::create([
            'reporter_id' => $criticalReporter->id,
            'reportable_type' => Post::class,
            'reportable_id' => $post->id,
            'category' => 'Threats',
            'status' => 'open',
            'mod_status' => 'pending',
            'mod_subject' => 'content',
        ]);

        $queue = app(\App\Services\ModerationService::class)->queue('queue', null);

        $this->assertCount(100, $queue);
        $this->assertSame($criticalReport->id, $queue->first()['id']);
    }

    public function test_dismiss_resolves_report_and_prevents_community_resolution(): void
    {
        $author = User::factory()->create(['points' => 10]);
        $reporter = User::factory()->create();
        $moderator = User::factory()->create(['role' => 'moderator']);
        $voters = User::factory()->count(3)->create();
        $post = $this->makePost($author, 'DISMISSED-POST');
        $report = Report::create([
            'reporter_id' => $reporter->id,
            'reportable_type' => Post::class,
            'reportable_id' => $post->id,
            'category' => 'Threats',
            'status' => 'voting',
            'mod_status' => 'pending',
            'mod_subject' => 'content',
            'voting_started_at' => now()->subHour(),
            'voting_ends_at' => now()->addHours(23),
        ]);

        foreach ($voters as $voter) {
            ReportVote::create(['report_id' => $report->id, 'voter_id' => $voter->id, 'choice' => 'agree']);
        }

        $this->actingAs($moderator)
            ->post("/moderation/{$report->id}", ['action' => 'dismiss', 'reason' => 'No violation found'])
            ->assertSessionHasNoErrors();

        $report = $report->fresh();
        $this->assertSame(Report::RESOLVED, $report->status);
        $this->assertSame('moderator_cleared', $report->outcome);
        $this->assertNotNull($report->resolved_at);
        $this->assertTrue($report->voting_ends_at->equalTo($report->resolved_at));
        $this->assertSame(10, $author->fresh()->points);
        $this->assertNull($post->fresh()->removed_at);

        // Simulate a stale active-vote entry reaching resolution after the moderator dismissal.
        $staleReport = $report->replicate();
        $staleReport->id = $report->id;
        $staleReport->setAttribute('status', 'voting');
        $resolve = new \ReflectionMethod(ReportService::class, 'resolve');
        $resolve->invoke(app(ReportService::class), $staleReport);

        $this->assertSame('moderator_cleared', $report->fresh()->outcome);
        $this->assertSame(10, $author->fresh()->points);
        $this->assertNull($post->fresh()->removed_at);
    }

    public function test_moderator_can_refer_remove_and_restore_and_guidance_can_mark_handled(): void
    {
        $author = User::factory()->create();
        $reporter = User::factory()->create();
        $mod = User::factory()->create(['role' => 'moderator']);
        $guidance = User::factory()->create(['role' => 'guidance']);
        $post = $this->makePost($author, 'CASE-POST');

        $this->actingAs($reporter)->post("/report/post/{$post->id}", ['category' => 'Threats']);
        $report = Report::firstOrFail();

        $this->actingAs($mod)->post("/moderation/{$report->id}", ['action' => 'refer', 'reason' => 'Possible danger'])->assertSessionHasNoErrors();
        $this->assertNotNull($report->fresh()->referred_guidance_at);
        $this->assertTrue(Alert::where('user_id', $guidance->id)->exists());

        $this->actingAs($mod)->post("/moderation/{$report->id}", ['action' => 'remove', 'reason' => 'Threat'])->assertSessionHasNoErrors();
        $this->assertNotNull($post->fresh()->removed_at);
        $this->assertSame('moderator', $post->fresh()->removed_by);

        // Guidance can only mark handled, not remove/ban.
        $this->actingAs($guidance)->post("/moderation/{$report->id}", ['action' => 'ban', 'reason' => 'nope'])->assertSessionHasErrors('report');
        $this->actingAs($guidance)->post("/moderation/{$report->id}", ['action' => 'handled', 'reason' => 'Student contacted'])->assertSessionHasNoErrors();
        $this->assertNotNull($report->fresh()->guidance_handled_at);

        // History lists every action with its reason.
        $this->actingAs($mod)->get('/moderation?tab=history')->assertSee('Possible danger')->assertSee('Student contacted');
    }

    public function test_admin_can_unmask_reporter_or_content_author_and_the_action_is_logged(): void
    {
        $author = User::factory()->create();
        $reporter = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $falseReportPost = $this->makePost($author, 'FALSE-REPORT-POST');
        $falseReport = $this->reportAndVote(
            $falseReportPost,
            $reporter,
            'Spam',
            ['false_report', 'false_report', 'false_report', 'agree', 'wrong_tag'],
        );

        $this->actingAs($admin)
            ->post("/moderation/{$falseReport->id}/unmask", ['reason' => 'Privacy investigation'])
            ->assertSessionHas('report_status', "Identity unmasked: {$reporter->email}")
            ->assertDontSee($reporter->email);

        $this->assertDatabaseHas('mod_actions', [
            'report_id' => $falseReport->id,
            'moderator_id' => $admin->id,
            'target_user_id' => $reporter->id,
            'action' => 'unmask',
            'reason' => 'Privacy investigation',
        ]);

        $contentAuthor = User::factory()->create();
        $contentReporter = User::factory()->create();
        $contentPost = $this->makePost($contentAuthor, 'CONTENT-REPORT-POST');
        $this->actingAs($contentReporter)->post("/report/post/{$contentPost->id}", ['category' => 'Threats']);
        $contentReport = Report::latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->post("/moderation/{$contentReport->id}/unmask", ['reason' => 'Safety investigation'])
            ->assertSessionHas('report_status', "Identity unmasked: {$contentAuthor->email}")
            ->assertDontSee($contentAuthor->email);

        $this->assertDatabaseHas('mod_actions', [
            'report_id' => $contentReport->id,
            'moderator_id' => $admin->id,
            'target_user_id' => $contentAuthor->id,
            'action' => 'unmask',
            'reason' => 'Safety investigation',
        ]);
    }

    public function test_muted_user_can_read_but_not_write_and_banned_user_is_locked_out(): void
    {
        $author = User::factory()->create();
        $post = $this->makePost($author);
        $muted = User::factory()->create(['muted_until' => now()->addDays(3)]);
        $banned = User::factory()->create(['banned_at' => now()]);

        $this->actingAs($muted)->get('/feed')->assertOk();
        $this->actingAs($muted)->from('/feed')->post("/post/{$post->id}/comments", ['body' => 'hi'])->assertSessionHasErrors('report');
        $this->assertSame(0, Comment::count());

        $this->actingAs($banned)->get('/feed')->assertForbidden()->assertSee('suspended');
    }

    public function test_moderators_can_unmute_and_unban_users_from_the_queue(): void
    {
        $moderator = User::factory()->create(['role' => 'moderator']);
        $mutedUser = User::factory()->create(['muted_until' => now()->addDays(3)]);
        $bannedUser = User::factory()->create(['banned_at' => now()]);
        $mutedPost = $this->makePost($mutedUser, 'MUTED-USER-POST');
        $bannedPost = $this->makePost($bannedUser, 'BANNED-USER-POST');
        $mutedReport = Report::create([
            'reporter_id' => User::factory()->create()->id,
            'reportable_type' => Post::class,
            'reportable_id' => $mutedPost->id,
            'category' => 'Threats',
            'status' => 'open',
            'mod_status' => 'pending',
            'mod_subject' => 'content',
        ]);
        $bannedReport = Report::create([
            'reporter_id' => User::factory()->create()->id,
            'reportable_type' => Post::class,
            'reportable_id' => $bannedPost->id,
            'category' => 'Threats',
            'status' => 'open',
            'mod_status' => 'pending',
            'mod_subject' => 'content',
        ]);

        $this->actingAs($moderator)->get('/moderation')
            ->assertOk()
            ->assertSee('Unmute')
            ->assertSee('Unban');

        $this->actingAs($moderator)
            ->post("/moderation/{$mutedReport->id}", ['action' => 'unmute', 'reason' => 'Mute no longer needed'])
            ->assertSessionHasNoErrors();
        $this->actingAs($moderator)
            ->post("/moderation/{$bannedReport->id}", ['action' => 'unban', 'reason' => 'Ban no longer needed'])
            ->assertSessionHasNoErrors();

        $this->assertNull($mutedUser->fresh()->muted_until);
        $this->assertNull($bannedUser->fresh()->banned_at);
        $this->assertDatabaseHas('mod_actions', [
            'report_id' => $mutedReport->id,
            'moderator_id' => $moderator->id,
            'target_user_id' => $mutedUser->id,
            'action' => 'unmute',
            'reason' => 'Mute no longer needed',
        ]);
        $this->assertDatabaseHas('mod_actions', [
            'report_id' => $bannedReport->id,
            'moderator_id' => $moderator->id,
            'target_user_id' => $bannedUser->id,
            'action' => 'unban',
            'reason' => 'Ban no longer needed',
        ]);
    }

    public function test_comment_creates_an_alert_that_opens_the_thread_and_marks_read(): void
    {
        [$author, $commenter] = User::factory()->count(2)->create();
        $post = $this->makePost($author);

        $this->actingAs($commenter)->post("/post/{$post->id}/comments", ['body' => 'Try checking the portal first', 'anonymous' => '1']);
        $alert = Alert::where('user_id', $author->id)->firstOrFail();

        $this->assertSame('New comment on your post', $alert->title);
        $this->assertNull($alert->read_at);
        $this->assertFalse(Alert::where('user_id', $commenter->id)->exists());

        $this->actingAs($author)->get('/alerts')->assertOk()->assertSee('New comment on your post')->assertSee('Try checking the');
        $this->actingAs($author)->get("/alerts/{$alert->id}")->assertRedirect(route('post.show', $post->id).'#comment-'.Comment::firstOrFail()->id);
        $this->assertNotNull($alert->fresh()->read_at);
    }

    public function test_notice_alert_shows_the_reason_page(): void
    {
        $user = User::factory()->create();
        $alert = Alert::create(['user_id' => $user->id, 'kind' => 'notice', 'title' => 'Notice from moderators', 'body' => 'Warning: be kind']);

        $this->actingAs($user)->get("/alerts/{$alert->id}")->assertOk()->assertSee('Warning: be kind');
        $this->assertNotNull($alert->fresh()->read_at);
        $this->actingAs(User::factory()->create())->get("/alerts/{$alert->id}")->assertNotFound();
    }

    public function test_alert_urls_redirect_to_a_local_path_even_when_the_stored_url_has_an_external_host(): void
    {
        $user = User::factory()->create();
        $alert = Alert::create([
            'user_id' => $user->id,
            'kind' => 'comment',
            'title' => 'New comment',
            'url' => 'https://evil.example//evil.example/path?tab=queue#comment-5',
        ]);

        $this->actingAs($user)->get("/alerts/{$alert->id}")
            ->assertRedirect(url('/evil.example/path?tab=queue#comment-5'));

        $alertWithoutPath = Alert::create([
            'user_id' => $user->id,
            'kind' => 'notice',
            'title' => 'Notice',
            'url' => 'https://evil.example',
        ]);

        $this->actingAs($user)->get("/alerts/{$alertWithoutPath->id}")
            ->assertRedirect(url('/'));
    }

    public function test_accepting_an_answer_gives_points_once_and_only_the_post_author_can(): void
    {
        [$author, $answerer, $other] = User::factory()->count(3)->create();
        $post = $this->makePost($author);
        $first = Comment::create(['post_id' => $post->id, 'author_id' => $answerer->id, 'body' => 'answer one', 'is_anonymous' => true]);
        $second = Comment::create(['post_id' => $post->id, 'author_id' => $other->id, 'body' => 'answer two', 'is_anonymous' => true]);

        $this->actingAs($other)->post("/post/{$post->id}/comments/{$first->id}/accept")->assertSessionHasErrors('report');

        $this->actingAs($author)->post("/post/{$post->id}/comments/{$first->id}/accept");
        $this->assertTrue($first->fresh()->is_accepted);
        $this->assertSame(5, $answerer->fresh()->points);
        $this->assertTrue(Alert::where('user_id', $answerer->id)->where('title', 'Your answer was marked helpful +5')->exists());

        // Picking another answer moves the badge and the points.
        $this->actingAs($author)->post("/post/{$post->id}/comments/{$second->id}/accept");
        $this->assertFalse($first->fresh()->is_accepted);
        $this->assertTrue($second->fresh()->is_accepted);
        $this->assertSame(0, $answerer->fresh()->points);
        $this->assertSame(5, $other->fresh()->points);

        $this->actingAs($author)->get("/post/{$post->id}")->assertSee('Accepted');
    }

    public function test_search_finds_posts_and_skips_removed_ones(): void
    {
        $user = User::factory()->create();
        $this->makePost($user, 'student assistant for ITPM');
        Post::create(['author_id' => $user->id, 'category' => 'Ask', 'body' => 'assistant removed one', 'is_anonymous' => true, 'removed_at' => now(), 'removed_by' => 'community']);

        $this->actingAs($user)->get('/search?q=assistant')->assertOk()->assertSee('student assistant for ITPM')->assertDontSee('assistant removed one');
        $this->actingAs($user)->get('/search?q=zzzz')->assertSee('No posts found');
        $this->actingAs($user)->get('/search')->assertSee('at least 2 letters');
    }

    public function test_profile_name_my_posts_my_comments_and_static_pages(): void
    {
        $user = User::factory()->create();
        $post = $this->makePost($user, 'MY-OWN-POST');
        Comment::create(['post_id' => $post->id, 'author_id' => $user->id, 'body' => 'MY-OWN-COMMENT', 'is_anonymous' => true]);

        $this->actingAs($user)->post('/me/name', ['display_name' => 'Mika R.'])->assertSessionHasNoErrors();
        $this->assertSame('Mika R.', User::find($user->id)->display_name);

        $user = $user->fresh();
        $this->actingAs($user)->get('/me')->assertOk()->assertSee('Mika R.')->assertSee('My posts');
        $this->actingAs($user)->get('/me/posts')->assertOk()->assertSee('MY-OWN-POST');
        $this->actingAs($user)->get('/me/comments')->assertOk()->assertSee('MY-OWN-COMMENT');
        $this->actingAs($user)->get('/rules')->assertOk()->assertSee('How reports work');
        $this->actingAs($user)->get('/privacy')->assertOk();
        $this->actingAs($user)->get('/help')->assertOk()->assertSee('1553');
        $this->actingAs($user)->get('/me')->assertDontSee('Moderation queue');
        $this->actingAs(User::factory()->create(['role' => 'moderator']))->get('/me')->assertSee('Moderation queue');
    }

    public function test_photo_upload_is_re_encoded_and_shown(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/post', [
            'category' => 'Share', 'body' => 'look at this', 'anonymous' => '1',
            'photo' => UploadedFile::fake()->image('pic.png', 1600, 900),
        ])->assertSessionHasNoErrors();

        $post = Post::firstOrFail();
        $this->assertNotNull($post->image_path);
        $this->assertStringEndsWith('.jpg', $post->image_path);
        $this->assertFileExists(public_path($post->image_path));
        $this->assertLessThanOrEqual(1280, getimagesize(public_path($post->image_path))[0]);

        $this->actingAs($user)->get('/feed')->assertSee($post->image_path);

        @unlink(public_path($post->image_path));

        $this->actingAs($user)->post('/post', ['category' => 'Share', 'body' => 'bad file', 'photo' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertSessionHasErrors('photo');
    }

    public function test_role_command(): void
    {
        $user = User::factory()->create(['email' => 'mod@olfu.test']);

        $this->artisan('community:role', ['email' => 'mod@olfu.test', 'role' => 'moderator'])->assertSuccessful();
        $this->assertSame('moderator', User::find($user->id)->role);

        $this->artisan('community:role', ['email' => 'mod@olfu.test', 'role' => 'wizard'])->assertFailed();
        $this->artisan('community:role', ['email' => 'nobody@olfu.test', 'role' => 'admin'])->assertFailed();
    }
}
