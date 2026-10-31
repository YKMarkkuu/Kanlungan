<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_create_post_and_comment(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/post', ['category' => 'Share', 'body' => 'hello world', 'anonymous' => '1'])->assertRedirect('/feed');
        $post = Post::firstOrFail();

        $this->actingAs($user)->post("/post/{$post->id}/comments", ['body' => 'first!', 'anonymous' => '1'])->assertRedirect("/post/{$post->id}");
        $this->assertSame(1, Comment::count());
        $this->actingAs($user)->get("/post/{$post->id}")->assertOk()->assertSee('first!')->assertDontSee($user->email);
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
