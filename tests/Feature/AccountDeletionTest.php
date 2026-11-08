<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Block;
use App\Models\Bookmark;
use App\Models\Comment;
use App\Models\OtpCode;
use App\Models\PointLog;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Services\AlertService;
use App\Services\PointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(User $author, string $body = 'a normal post', string $category = 'Ask'): Post
    {
        return Post::create([
            'author_id' => $author->id,
            'category' => $category,
            'body' => $body,
            'is_anonymous' => true,
        ]);
    }

    private function deleteAs(User $user, string $confirm = 'DELETE')
    {
        return $this->actingAs($user)->post(route('community.me.delete'), ['confirm' => $confirm]);
    }

    public function test_deleting_tombstones_the_user_and_logs_them_out(): void
    {
        $user = User::factory()->create([
            'display_name' => 'Mika R.',
            'student_id' => '12345678901',
            'verified_at' => now(),
        ]);
        $user->forceFill([
            'points' => 40,
            'accepted_terms_at' => now(),
            'terms_version' => config('kanlungan.terms_version'),
        ])->save();

        $this->deleteAs($user)->assertRedirect('/')->assertSessionHas('status');
        $this->assertGuest();

        $fresh = User::findOrFail($user->id);
        $this->assertSame('Deleted User', $fresh->name);
        $this->assertSame("deleted-{$user->id}@deleted.invalid", $fresh->email);

        foreach (['display_name', 'student_id', 'avatar_path', 'email_verified_at', 'verified_at', 'accepted_terms_at', 'terms_version'] as $column) {
            $this->assertNull($fresh->{$column}, "{$column} should be null");
        }

        $this->assertNotNull($fresh->deleted_at);
        $this->assertSame(0, (int) $fresh->points);
    }

    public function test_wrong_confirmation_word_changes_nothing(): void
    {
        $user = User::factory()->create();

        $this->deleteAs($user, 'delete')->assertSessionHasErrors('confirm');
        $this->deleteAs($user, '')->assertSessionHasErrors('confirm');

        $this->assertAuthenticatedAs($user);
        $this->assertNull(User::findOrFail($user->id)->deleted_at);
        $this->assertSame($user->email, User::findOrFail($user->id)->email);
    }

    public function test_text_is_erased_but_other_students_replies_survive(): void
    {
        [$author, $other] = User::factory()->count(2)->create();
        $post = $this->makePost($author, 'MY-PRIVATE-WORDS');
        $mine = Comment::create([
            'post_id' => $post->id,
            'author_id' => $author->id,
            'body' => 'MY-COMMENT-WORDS',
            'is_anonymous' => true,
        ]);
        $reply = Comment::create([
            'post_id' => $post->id,
            'author_id' => $other->id,
            'parent_id' => $mine->id,
            'body' => 'OTHER-REPLY-WORDS',
            'is_anonymous' => true,
        ]);

        $this->deleteAs($author)->assertRedirect('/');

        $post->refresh();
        $this->assertNotNull($post->removed_at);
        $this->assertSame('author', $post->removed_by);
        $this->assertSame('[deleted]', $post->body);

        $mine->refresh();
        $this->assertNotNull($mine->removed_at);
        $this->assertSame('[deleted]', $mine->body);

        $this->assertSame('OTHER-REPLY-WORDS', $reply->fresh()->body);
        $this->assertNull($reply->fresh()->removed_at);

        $this->actingAs($other)->get("/post/{$post->id}")->assertOk()
            ->assertSee('OTHER-REPLY-WORDS')
            ->assertSee('removed by its author')
            ->assertDontSee('MY-PRIVATE-WORDS')
            ->assertDontSee('MY-COMMENT-WORDS');
    }

    public function test_avatar_and_post_photos_are_deleted_from_disk(): void
    {
        Storage::fake('supabase');

        foreach (['uploads/posts', 'uploads/avatars'] as $directory) {
            if (! is_dir(public_path($directory))) {
                mkdir(public_path($directory), 0775, true);
            }
        }

        $avatar = 'uploads/avatars/'.Str::uuid().'.jpg';
        $photo = 'uploads/posts/'.Str::uuid().'.jpg';
        file_put_contents(public_path($avatar), 'avatar');
        file_put_contents(public_path($photo), 'photo');

        $user = User::factory()->create(['avatar_path' => $avatar]);
        $post = $this->makePost($user);
        $post->images()->create(['path' => $photo, 'position' => 0, 'width' => 1, 'height' => 1]);

        $this->deleteAs($user)->assertRedirect('/');

        $this->assertFileDoesNotExist(public_path($avatar));
        $this->assertFileDoesNotExist(public_path($photo));
        $this->assertSame(0, $post->images()->count());
        $this->assertNull(User::findOrFail($user->id)->avatar_path);

        $remoteAvatar = 'avatars/'.Str::uuid().'.jpg';
        $remotePhoto = 'posts/'.Str::uuid().'.jpg';
        Storage::disk('supabase')->put($remoteAvatar, 'avatar');
        Storage::disk('supabase')->put($remotePhoto, 'photo');
        $remoteUser = User::factory()->create(['avatar_path' => $remoteAvatar]);
        $remotePost = $this->makePost($remoteUser);
        $remotePost->images()->create(['path' => $remotePhoto, 'position' => 0, 'width' => 1, 'height' => 1]);

        $this->deleteAs($remoteUser)->assertRedirect('/');

        $this->assertFalse(Storage::disk('supabase')->exists($remoteAvatar));
        $this->assertFalse(Storage::disk('supabase')->exists($remotePhoto));
        $this->assertSame(0, $remotePost->images()->count());
    }

    public function test_private_data_is_removed_and_reports_lose_their_free_text(): void
    {
        [$user, $other] = User::factory()->count(2)->create();
        $email = $user->email;
        $post = $this->makePost($other);

        Alert::create(['user_id' => $user->id, 'kind' => 'notice', 'title' => 'x', 'body' => 'secret snippet']);
        Bookmark::create(['user_id' => $user->id, 'post_id' => $post->id]);
        Block::create(['blocker_id' => $user->id, 'blocked_id' => $other->id]);
        Block::create(['blocker_id' => $other->id, 'blocked_id' => $user->id]);
        PointLog::create(['user_id' => $user->id, 'amount' => 3, 'reason' => 'helpful_received']);
        OtpCode::create([
            'email' => $email,
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
        ]);
        $report = Report::create([
            'reporter_id' => $user->id,
            'reportable_type' => Post::class,
            'reportable_id' => $post->id,
            'category' => 'Spam',
            'details' => 'PRIVATE-REPORT-NOTE',
            'status' => Report::OPEN,
        ]);

        $this->deleteAs($user)->assertRedirect('/');

        $this->assertSame(0, Alert::where('user_id', $user->id)->count());
        $this->assertSame(0, Bookmark::where('user_id', $user->id)->count());
        $this->assertSame(0, Block::count());
        $this->assertSame(0, PointLog::where('user_id', $user->id)->count());
        $this->assertSame(0, OtpCode::where('email', $email)->count());
        $this->assertNull($report->fresh()->details);
    }

    public function test_banned_accounts_cannot_delete_themselves(): void
    {
        $user = User::factory()->create(['banned_at' => now()]);

        $this->deleteAs($user)->assertSessionHasErrors('report');

        $fresh = User::findOrFail($user->id);
        $this->assertNull($fresh->deleted_at);
        $this->assertSame($user->email, $fresh->email);
    }

    public function test_staff_accounts_cannot_delete_themselves(): void
    {
        $moderator = User::factory()->create(['role' => 'moderator']);

        $this->deleteAs($moderator)->assertSessionHasErrors('report');

        $this->assertNull(User::findOrFail($moderator->id)->deleted_at);
        $this->assertSame('moderator', User::findOrFail($moderator->id)->role);
    }

    public function test_muted_students_may_still_delete_their_data(): void
    {
        $user = User::factory()->create(['muted_until' => now()->addDays(3)]);

        $this->deleteAs($user)->assertRedirect('/');

        $this->assertNotNull(User::findOrFail($user->id)->deleted_at);
    }

    public function test_moderators_are_alerted_before_a_safety_post_is_wiped(): void
    {
        [$author, $reporter] = User::factory()->count(2)->create();
        $moderator = User::factory()->create(['role' => 'moderator']);
        $admin = User::factory()->create(['role' => 'admin']);
        $guidance = User::factory()->create(['role' => 'guidance']);
        $post = $this->makePost($author, 'I do not want to be here anymore');

        $report = Report::create([
            'reporter_id' => $reporter->id,
            'reportable_type' => Post::class,
            'reportable_id' => $post->id,
            'category' => 'Self-harm',
            'status' => Report::ESCALATED,
            'mod_status' => 'pending',
            'mod_subject' => 'content',
        ]);

        $this->deleteAs($author)->assertRedirect('/');

        foreach ([$moderator, $admin] as $staff) {
            $alert = Alert::where('user_id', $staff->id)->where('title', 'Safety case: author deleted account')->first();
            $this->assertNotNull($alert);
            $this->assertStringContainsString('I do not want to be here anymore', $alert->body);
        }

        $this->assertFalse(Alert::where('user_id', $guidance->id)->exists());
        $this->assertSame('[deleted]', $post->fresh()->body);
        $this->assertSame(Report::ESCALATED, $report->fresh()->status);
        $this->assertSame('pending', $report->fresh()->mod_status);
    }

    public function test_non_safety_reports_on_deleted_content_are_closed(): void
    {
        [$author, $reporter] = User::factory()->count(2)->create();
        $post = $this->makePost($author);
        $report = Report::create([
            'reporter_id' => $reporter->id,
            'reportable_type' => Post::class,
            'reportable_id' => $post->id,
            'category' => 'Spam',
            'status' => Report::VOTING,
            'voting_started_at' => now(),
            'voting_ends_at' => now()->addDay(),
        ]);

        $this->deleteAs($author)->assertRedirect('/');

        $this->assertSame(Report::RESOLVED, $report->fresh()->status);
        $this->assertSame('author_deleted', $report->fresh()->outcome);
    }

    public function test_deleted_authors_cannot_be_unmasked_or_moderated_against(): void
    {
        [$author, $reporter] = User::factory()->count(2)->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $post = $this->makePost($author);
        $author->forceFill(['deleted_at' => now()])->save();
        $report = Report::create([
            'reporter_id' => $reporter->id,
            'reportable_type' => Post::class,
            'reportable_id' => $post->id,
            'category' => 'Threats',
            'status' => Report::ESCALATED,
            'mod_status' => 'pending',
            'mod_subject' => 'content',
        ]);

        $this->actingAs($admin)->get(route('moderation'))->assertOk()
            ->assertSee('name="action" value="dismiss"', false)
            ->assertDontSee('name="action" value="warn"', false)
            ->assertDontSee('name="action" value="restore"', false);

        $this->actingAs($admin)->post(route('moderation.unmask', $report), [
            'reason' => 'privacy review',
        ])->assertSessionHasErrors('report');
    }

    public function test_stale_sessions_of_deleted_accounts_are_rejected_on_every_authenticated_route(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['deleted_at' => now()])->save();

        $this->actingAs($user)->get('/feed')->assertRedirect('/');
        $this->assertGuest();

        $this->actingAs($user)->getJson('/alerts/unread-count')->assertUnauthorized();
        $this->actingAs($user)->post('/block/post/1')->assertRedirect('/');
    }

    public function test_the_same_email_and_student_id_can_register_again_after_deletion(): void
    {
        $email = 'student7777pam@student.fatima.edu.ph';
        $user = User::factory()->create(['email' => $email, 'student_id' => '11111111111']);

        $this->deleteAs($user)->assertRedirect('/');
        $this->assertGuest();

        OtpCode::create([
            'email' => $email,
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(OtpCode::TTL_MINUTES),
        ]);
        $this->postJson('/auth/register', [
            'email' => $email,
            'code' => '123456',
            'student_id' => '11111111111',
            'terms' => true,
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['email' => $email, 'student_id' => '11111111111']);

        $newUser = User::where('email', $email)->firstOrFail();
        $this->assertNotSame($user->id, $newUser->id);
        $this->assertAuthenticatedAs($newUser);
    }

    public function test_tombstones_never_earn_points_or_receive_alerts_again(): void
    {
        $user = User::factory()->create();
        $this->deleteAs($user);

        app(PointService::class)->award($user->id, 5, 'helpful_received');
        app(AlertService::class)->notify($user->id, 'notice', 'Hello?');

        $this->assertSame(0, (int) User::findOrFail($user->id)->points);
        $this->assertSame(0, PointLog::where('user_id', $user->id)->count());
        $this->assertSame(0, Alert::where('user_id', $user->id)->count());
    }
}
