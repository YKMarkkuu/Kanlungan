<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Block;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Services\CommunityService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Needs a working Laravel app with the users table (+ display_name) and a UserFactory.
 * Run: php artisan test
 */
class CommunityFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(User $author): Post
    {
        return Post::create(['author_id' => $author->id, 'category' => 'Share', 'body' => 'hello there', 'is_anonymous' => true]);
    }

    public function test_notification_dropdown_and_unread_count_are_scoped_to_the_current_user(): void
    {
        [$viewer, $otherUser] = User::factory()->count(2)->create();

        for ($i = 1; $i <= 6; $i++) {
            Alert::create([
                'user_id' => $viewer->id,
                'kind' => 'notice',
                'title' => 'Viewer notification '.$i,
            ]);
        }

        Alert::create([
            'user_id' => $otherUser->id,
            'kind' => 'notice',
            'title' => 'Other user notification',
        ]);

        $this->actingAs($viewer)->get('/alerts/unread-count')
            ->assertOk()
            ->assertJson(['count' => 6]);

        $feedResponse = $this->get('/feed')->assertOk();

        $this->assertStringContainsString('no-cache', $feedResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $feedResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('must-revalidate', $feedResponse->headers->get('Cache-Control'));
        $feedResponse
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Expires', '0')
            ->assertSee('Viewer notification 6')
            ->assertSee('Viewer notification 2')
            ->assertDontSee('Viewer notification 1')
            ->assertDontSee('Other user notification')
            ->assertSee('data-notification-dropdown', false)
            ->assertSee('Mark all as read');
    }

    public function test_blocking_an_author_keeps_content_visible_but_prevents_interactions(): void
    {
        [$viewer, $blockedAuthor, $otherAuthor] = User::factory()->count(3)->create();
        $blockedAuthor->forceFill(['display_name' => 'Private Blocked Name'])->save();
        $blockedPost = $this->makePost($blockedAuthor);
        $visiblePost = $this->makePost($otherAuthor);
        $blockedPost->update(['body' => 'blocked author post']);
        $visiblePost->update(['body' => 'visible author post']);
        Comment::create([
            'post_id' => $visiblePost->id,
            'author_id' => $blockedAuthor->id,
            'body' => 'hidden blocked comment',
            'is_anonymous' => true,
        ]);
        Comment::create([
            'post_id' => $visiblePost->id,
            'author_id' => $otherAuthor->id,
            'body' => 'visible comment',
            'is_anonymous' => true,
        ]);

        $this->actingAs($viewer)->post("/block/post/{$blockedPost->id}")
            ->assertRedirect(route('feed'))
            ->assertSessionHas('report_status', 'Blocked. You can still see their posts and comments, but you cannot interact with them.');

        $this->assertSame(1, Block::where('blocker_id', $viewer->id)->where('blocked_id', $blockedAuthor->id)->count());
        $this->actingAs($viewer)->get(route('feed'))
            ->assertOk()
            ->assertSee($blockedPost->body)
            ->assertSee($visiblePost->body)
            ->assertSee('data-comments-count>2</span>', false);
        $this->actingAs($viewer)->get("/post/{$blockedPost->id}")->assertOk();
        $this->actingAs($viewer)->get("/post/{$visiblePost->id}")
            ->assertOk()
            ->assertDontSee('hidden blocked comment')
            ->assertSee('visible comment');
        $this->actingAs($viewer)->postJson("/post/{$blockedPost->id}/comments", [
            'body' => 'comment on a blocked author post',
            'anonymous' => '1',
        ])->assertForbidden()
            ->assertSeeText('You have blocked this user.');
        $this->actingAs($viewer)->post("/post/{$blockedPost->id}/like")
            ->assertForbidden()
            ->assertSeeText('You have blocked this user.');
        $blockedComment = Comment::where('body', 'hidden blocked comment')->firstOrFail();
        $this->actingAs($viewer)->post("/post/{$visiblePost->id}/comments/{$blockedComment->id}/like")
            ->assertForbidden()
            ->assertSeeText('You have blocked this user.');
        $this->actingAs($viewer)->post("/post/{$visiblePost->id}/comments/{$blockedComment->id}/helpful")
            ->assertForbidden()
            ->assertSeeText('You have blocked this user.');
        $this->actingAs($viewer)->postJson("/post/{$visiblePost->id}/comments", [
            'body' => 'new visible comment',
            'anonymous' => '1',
        ])->assertCreated()->assertJson(['comments_count' => 3]);

        $block = Block::where('blocker_id', $viewer->id)->firstOrFail();
        $this->actingAs($viewer)->get('/me')
            ->assertOk()
            ->assertSee('Blocked Users')
            ->assertSee(route('block.destroy', $block->id))
            ->assertSee('Blocked on '.$block->created_at->format('M j, Y'))
            ->assertDontSee('Private Blocked Name')
            ->assertDontSee($blockedAuthor->email);
    }

    public function test_block_routes_prevent_self_blocking_and_only_allow_the_blocker_to_unblock(): void
    {
        [$viewer, $author, $otherUser] = User::factory()->count(3)->create();
        $ownPost = $this->makePost($viewer);
        $targetPost = $this->makePost($author);

        $this->actingAs($viewer)->post("/block/post/{$ownPost->id}")->assertSessionHasErrors('report');
        $this->actingAs($viewer)->post("/block/post/{$targetPost->id}");

        $block = Block::where('blocker_id', $viewer->id)->where('blocked_id', $author->id)->firstOrFail();
        $this->actingAs($otherUser)->post("/blocks/{$block->id}/unblock")->assertNotFound();
        $this->assertNotNull($block->fresh());

        $viewer->forceFill(['banned_at' => now()])->save();
        $this->actingAs($viewer)->post("/blocks/{$block->id}/unblock")
            ->assertSessionHas('report_status', 'Unblocked.');
        $this->assertNull($block->fresh());
    }

    public function test_create_post_and_comment(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/post', ['category' => 'Share', 'body' => 'hello world', 'anonymous' => '1'])->assertRedirect('/feed');
        $post = Post::firstOrFail();

        $this->actingAs($user)->post("/post/{$post->id}/comments", ['body' => 'first!', 'anonymous' => '1'])->assertRedirect("/post/{$post->id}");
        $this->assertSame(1, Comment::count());
        $this->actingAs($user)->get("/post/{$post->id}")
            ->assertOk()
            ->assertSee('first!')
            ->assertSee('<textarea class="input" name="body" id="comment-body"', false)
            ->assertDontSee($user->email);
    }

    public function test_post_and_comment_cards_include_full_timestamp_titles(): void
    {
        [$author, $commenter] = User::factory()->count(2)->create();
        $post = $this->makePost($author);
        $comment = Comment::create([
            'post_id' => $post->id,
            'author_id' => $commenter->id,
            'body' => 'A timestamped comment',
            'is_anonymous' => true,
        ]);

        $this->actingAs($commenter)->get("/post/{$post->id}")
            ->assertOk()
            ->assertSee($post->created_at->format('F j, Y, g:i A'))
            ->assertSee($comment->created_at->format('F j, Y, g:i A'));
    }

    public function test_only_post_author_can_delete_and_removal_hides_content(): void
    {
        [$author, $other] = User::factory()->count(2)->create();
        $post = $this->makePost($author);

        $this->actingAs($author)->get('/feed')
            ->assertOk()
            ->assertSee(route('post.destroy', $post->id))
            ->assertSee('Delete this post?')
            ->assertDontSee(route('report.store', ['post', $post->id]));

        $this->actingAs($other)->delete("/post/{$post->id}")->assertForbidden();
        $this->assertNull($post->fresh()->removed_at);

        $this->actingAs($author)->delete("/post/{$post->id}")
            ->assertRedirect('/feed')
            ->assertSessionHas('status', 'Post deleted.');

        $this->assertNotNull($post->fresh()->removed_at);
        $this->assertSame('author', $post->fresh()->removed_by);
        $this->actingAs($other)->get("/post/{$post->id}")
            ->assertOk()
            ->assertSee('removed by its author');
    }

    public function test_comment_actions_are_contextual_and_only_author_can_delete(): void
    {
        [$postAuthor, $commentAuthor, $other] = User::factory()->count(3)->create();
        $post = $this->makePost($postAuthor);
        $comment = Comment::create([
            'post_id' => $post->id,
            'author_id' => $commentAuthor->id,
            'body' => 'Comment with contextual actions',
            'is_anonymous' => true,
        ]);

        $this->actingAs($commentAuthor)->get("/post/{$post->id}")
            ->assertOk()
            ->assertSee(route('comment.destroy', [$post->id, $comment->id]))
            ->assertSee('Delete this comment?')
            ->assertDontSee(route('report.store', ['comment', $comment->id]));

        $this->actingAs($other)->delete(route('comment.destroy', [$post->id, $comment->id]))
            ->assertForbidden();
        $this->assertNull($comment->fresh()->removed_at);

        $this->actingAs($commentAuthor)->delete(route('comment.destroy', [$post->id, $comment->id]))
            ->assertRedirect()
            ->assertSessionHas('status', 'Comment deleted.');

        $this->assertNotNull($comment->fresh()->removed_at);
        $this->assertSame('author', $comment->fresh()->removed_by);
    }

    public function test_json_post_creation_returns_redirect_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/post', [
            'category' => 'Share',
            'body' => 'hello from ajax',
            'anonymous' => '1',
        ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'redirect' => route('feed'),
            ]);
        $this->assertSame(1, Post::count());
        $response->assertSessionHas('status', 'Posted.');
    }

    public function test_feed_uses_cursor_pagination_and_keeps_category_filter_on_next_page(): void
    {
        $viewer = User::factory()->create();

        for ($i = 0; $i < 18; $i++) {
            $post = $this->makePost($viewer);
            $post->forceFill([
                'body' => "cursor-post-{$i}",
                'created_at' => now()->subMinutes($i),
            ])->save();
        }

        $firstPage = $this->actingAs($viewer)
            ->get(route('feed', ['category' => 'Share']))
            ->assertOk()
            ->assertSee('id="load-more-btn"', false);

        $posts = $firstPage->viewData('posts');
        $this->assertSame(15, $posts->count());
        $nextPageQuery = [];
        parse_str((string) parse_url($posts->nextPageUrl(), PHP_URL_QUERY), $nextPageQuery);
        $this->assertSame('Share', $nextPageQuery['category'] ?? null);
        $firstPage->assertSee('cursor-post-0')->assertDontSee('cursor-post-17');

        $secondPage = $this->actingAs($viewer)
            ->get($posts->nextPageUrl())
            ->assertOk()
            ->assertDontSee('id="load-more-btn"', false)
            ->assertSee('cursor-post-17')
            ->assertDontSee('cursor-post-0');

        $this->assertSame(3, $secondPage->viewData('posts')->count());
    }

    public function test_avatar_upload_is_cropped_and_hidden_on_anonymous_content(): void
    {
        $author = User::factory()->create();

        $this->actingAs($author)->post(route('profile.avatar'), [
            'avatar' => UploadedFile::fake()->image('profile.png', 300, 200),
        ])->assertRedirect()
            ->assertSessionHas('report_status', 'Profile picture updated.');

        $avatarPath = $author->fresh()->avatar_path;
        $this->assertNotNull($avatarPath);
        $this->assertFileExists(public_path($avatarPath));
        $imageSize = getimagesize(public_path($avatarPath));
        $this->assertSame([150, 150], [$imageSize[0], $imageSize[1]]);

        $anonymousPost = $this->makePost($author);
        $namedPost = Post::create([
            'author_id' => $author->id,
            'category' => 'Share',
            'body' => 'Named avatar post',
            'is_anonymous' => false,
        ]);
        $feed = app(CommunityService::class)->feed((int) $author->id);
        $postsById = $feed['posts']->getCollection()->keyBy('id');

        $this->assertNull($postsById[$anonymousPost->id]['avatar_path']);
        $this->assertSame($avatarPath, $postsById[$namedPost->id]['avatar_path']);

        $this->actingAs($author)->get('/me')
            ->assertOk()
            ->assertSee(asset($avatarPath))
            ->assertSee('Position your photo')
            ->assertSee('Drag to position and pinch to zoom')
            ->assertSee('cropper.min.js')
            ->assertDontSee('avatar-crop-x');

        @unlink(public_path($avatarPath));
    }

    public function test_json_comment_creation_returns_renderable_card_and_count(): void
    {
        [$author, $commenter] = User::factory()->count(2)->create();
        $post = $this->makePost($author);

        $response = $this->actingAs($commenter)->postJson("/post/{$post->id}/comments", [
            'body' => 'first ajax comment',
            'anonymous' => '1',
        ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'parent_id' => null,
                'comments_count' => 1,
            ])
            ->assertJsonStructure(['comment_id', 'html'])
            ->assertSee('first ajax comment');

        $commentId = $response->json('comment_id');
        $this->actingAs($commenter)->postJson("/post/{$post->id}/comments", [
            'body' => 'ajax reply',
            'parent_id' => $commentId,
            'anonymous' => '1',
        ])->assertCreated()
            ->assertJson([
                'parent_id' => $commentId,
                'comments_count' => 2,
            ])
            ->assertSee('ajax reply');
    }

    public function test_helpful_reactions_are_available_on_replies_and_award_points(): void
    {
        [$author, $replyAuthor, $viewer] = User::factory()->count(3)->create();
        $post = $this->makePost($author);
        $parent = Comment::create([
            'post_id' => $post->id,
            'author_id' => $author->id,
            'body' => 'Top-level comment',
            'is_anonymous' => true,
        ]);
        $reply = Comment::create([
            'post_id' => $post->id,
            'author_id' => $replyAuthor->id,
            'parent_id' => $parent->id,
            'body' => 'Helpful reply',
            'is_anonymous' => true,
        ]);

        $this->actingAs($viewer)->get("/post/{$post->id}")
            ->assertOk()
            ->assertSee('Helpful reply')
            ->assertSee('data-react="like"', false)
            ->assertSee('data-react="helpful"', false)
            ->assertSee('data-comment-id="'.$reply->id.'"', false);

        $this->actingAs($viewer)->post("/post/{$post->id}/comments/{$reply->id}/helpful")
            ->assertRedirect();

        $this->assertSame(1, $reply->reactions()->where('type', 'helpful')->count());
        $this->assertSame(3, $replyAuthor->fresh()->points);
    }

    public function test_like_toggles_and_gives_author_points(): void
    {
        [$author, $fan] = User::factory()->count(2)->create();
        $post = $this->makePost($author);

        $this->actingAs($fan)->post("/post/{$post->id}/like");
        $this->assertSame(1, $post->reactions()->count());
        $this->assertSame(1, $author->fresh()->points);

        $this->actingAs($fan)->post("/post/{$post->id}/like");
        $this->assertSame(0, $post->reactions()->count());
        $this->assertSame(0, $author->fresh()->points);
    }

    public function test_json_like_response_returns_the_current_state_and_count(): void
    {
        [$author, $fan] = User::factory()->count(2)->create();
        $post = $this->makePost($author);

        $this->actingAs($fan)->postJson("/post/{$post->id}/like")
            ->assertOk()
            ->assertJson(['status' => 'success', 'new_count' => 1, 'is_active' => true]);

        $this->actingAs($fan)->postJson("/post/{$post->id}/like")
            ->assertOk()
            ->assertJson(['status' => 'success', 'new_count' => 0, 'is_active' => false]);
    }

    public function test_json_reaction_requests_receive_restriction_responses(): void
    {
        [$author, $fan] = User::factory()->count(2)->create();
        $post = $this->makePost($author);

        $fan->forceFill(['muted_until' => now()->addHour()])->save();
        $this->actingAs($fan)->postJson("/post/{$post->id}/like")
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['report']]);

        $fan->forceFill(['muted_until' => null, 'banned_at' => now()])->save();
        $this->actingAs($fan)->postJson("/post/{$post->id}/like")
            ->assertForbidden()
            ->assertJson(['message' => 'Your account is suspended.']);
    }

    public function test_cannot_like_your_own_post(): void
    {
        $author = User::factory()->create();
        $post = $this->makePost($author);

        $this->actingAs($author)->post("/post/{$post->id}/like")->assertSessionHasErrors('report');
        $this->assertSame(0, $post->reactions()->count());
        $this->assertSame(0, $author->fresh()->points);
    }

    public function test_upheld_report_removes_content_and_moves_points(): void
    {
        $author = User::factory()->create(['points' => 20]);
        $reporter = User::factory()->create();
        $voters = User::factory()->count(5)->create();
        $post = $this->makePost($author);

        $this->actingAs($reporter)->post("/report/post/{$post->id}", ['category' => 'Spam']);
        $report = Report::firstOrFail();
        $this->actingAs($voters[0])->post("/reports/{$report->id}/start");

        foreach ($voters as $i => $voter) {
            $this->actingAs($voter)->post("/reports/{$report->id}/vote", ['choice' => $i < 4 ? 'agree' : 'false_report']);
        }

        $report->refresh();
        $this->assertSame('resolved', $report->status);
        $this->assertSame('upheld', $report->outcome);
        $this->assertSame(10, $author->fresh()->points);
        $this->assertSame(3, $reporter->fresh()->points);
        $this->actingAs($voters[0])->get("/post/{$post->id}")->assertSee('removed by community vote');
    }

    public function test_false_report_punishes_reporter(): void
    {
        $author = User::factory()->create();
        $reporter = User::factory()->create(['points' => 10]);
        $voters = User::factory()->count(5)->create();
        $post = $this->makePost($author);

        $this->actingAs($reporter)->post("/report/post/{$post->id}", ['category' => 'Threats']);
        $report = Report::firstOrFail();
        $this->actingAs($voters[0])->post("/reports/{$report->id}/start");
        foreach ($voters as $voter) {
            $this->actingAs($voter)->post("/reports/{$report->id}/vote", ['choice' => 'false_report']);
        }

        $this->assertSame('false_report', $report->fresh()->outcome);
        $this->assertSame(5, $reporter->fresh()->points);
    }

    public function test_author_and_reporter_cannot_vote_and_nobody_votes_twice(): void
    {
        $author = User::factory()->create();
        $reporter = User::factory()->create();
        $voter = User::factory()->create();
        $post = $this->makePost($author);

        $this->actingAs($reporter)->post("/report/post/{$post->id}", ['category' => 'Spam']);
        $report = Report::firstOrFail();
        $this->actingAs($voter)->post("/reports/{$report->id}/start");

        $this->actingAs($author)->post("/reports/{$report->id}/vote", ['choice' => 'false_report'])->assertSessionHasErrors('report');
        $this->actingAs($reporter)->post("/reports/{$report->id}/vote", ['choice' => 'agree'])->assertSessionHasErrors('report');
        $this->actingAs($voter)->post("/reports/{$report->id}/vote", ['choice' => 'agree']);
        $this->actingAs($voter)->post("/reports/{$report->id}/vote", ['choice' => 'agree'])->assertSessionHasErrors('report');
        $this->assertSame(1, $report->votes()->count());
    }

    public function test_report_vote_json_request_returns_success_without_redirecting(): void
    {
        $author = User::factory()->create();
        $reporter = User::factory()->create();
        $starter = User::factory()->create();
        $voter = User::factory()->create();
        $post = $this->makePost($author);

        $this->actingAs($reporter)->post("/report/post/{$post->id}", ['category' => 'Spam']);
        $report = Report::firstOrFail();
        $this->actingAs($starter)->post("/reports/{$report->id}/start");

        $this->actingAs($voter)->postJson("/reports/{$report->id}/vote", ['choice' => 'agree'])
            ->assertOk()
            ->assertJson(['status' => 'success', 'message' => 'Vote recorded.']);

        $this->assertSame(1, $report->votes()->count());
    }

    public function test_report_vote_redirect_flashes_verdicts_tab(): void
    {
        $author = User::factory()->create();
        $reporter = User::factory()->create();
        $starter = User::factory()->create();
        $voter = User::factory()->create();
        $post = $this->makePost($author);

        $this->actingAs($reporter)->post("/report/post/{$post->id}", ['category' => 'Spam']);
        $report = Report::firstOrFail();
        $this->actingAs($starter)->post("/reports/{$report->id}/start");

        $this->actingAs($voter)
            ->from('/alerts?tab=verdicts')
            ->post("/reports/{$report->id}/vote", ['choice' => 'agree', 'tab' => 'verdicts'])
            ->assertRedirect('/alerts?tab=verdicts')
            ->assertSessionHas('tab', 'verdicts');
    }

    public function test_cannot_report_own_post(): void
    {
        $author = User::factory()->create();
        $post = $this->makePost($author);

        $this->actingAs($author)->post("/report/post/{$post->id}", ['category' => 'Spam'])->assertSessionHasErrors('report');
        $this->assertSame(0, Report::count());
    }

    public function test_expired_vote_is_closed_by_sweep(): void
    {
        $author = User::factory()->create();
        $reporter = User::factory()->create();
        $voters = User::factory()->count(3)->create();
        $post = $this->makePost($author);

        $this->actingAs($reporter)->post("/report/post/{$post->id}", ['category' => 'Doxxing']);
        $report = Report::firstOrFail();
        $this->actingAs($voters[0])->post("/reports/{$report->id}/start");
        foreach ($voters as $voter) {
            $this->actingAs($voter)->post("/reports/{$report->id}/vote", ['choice' => 'agree']);
        }
        $this->assertSame('voting', $report->fresh()->status);

        $report->update(['voting_ends_at' => now()->subMinute()]);
        app(ReportService::class)->sweepDue();

        $this->assertSame('upheld', $report->fresh()->outcome);
    }
}
