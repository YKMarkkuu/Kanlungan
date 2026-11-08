<?php

namespace Tests\Feature;

use App\Mail\OtpCodeMail;
use App\Models\Comment;
use App\Models\ModAction;
use App\Models\OtpCode;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Support\Alias;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The staff dashboard (/staff): who can get in, what staff can and cannot see, and the decisions they make.
 * The privacy tests matter most: staff see reported content only, under its alias, and a reveal is logged.
 */
class StaffDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role = 'admin'): User
    {
        $user = User::factory()->create(['name' => ucfirst($role).' Staffer']);
        $user->forceFill(['role' => $role])->save();

        return $user->fresh();
    }

    /** A student with a real name, email and student ID, so tests can check none of it leaks. */
    private function student(array $overrides = []): User
    {
        return User::factory()->create($overrides + [
            'display_name' => 'Maria Santos',
            'student_id' => (string) random_int(10000000000, 99999999999),
        ]);
    }

    private function reportedPost(User $author, string $category = 'Sexual content', string $body = 'REPORTED-BODY', bool $anonymous = true): array
    {
        $post = Post::create(['author_id' => $author->id, 'category' => 'Vent', 'body' => $body, 'is_anonymous' => $anonymous]);
        $this->actingAs($this->student())->post("/report/post/{$post->id}", ['category' => $category, 'details' => 'bad words']);

        return [$post, Report::latest('id')->firstOrFail()];
    }

    /* ---------- getting in ---------- */

    public function test_staff_sign_in_sends_a_code_only_to_real_staff_and_says_the_same_thing_either_way(): void
    {
        Mail::fake();
        $staff = $this->staff('guidance');
        $student = $this->student();

        $known = $this->post('/staff/login/code', ['email' => $staff->email]);
        $stranger = $this->post('/staff/login/code', ['email' => 'nobody@fatima.edu.ph']);
        $studentTry = $this->post('/staff/login/code', ['email' => $student->email]);

        $known->assertRedirect('/staff/login')->assertSessionHas('status');
        $this->assertSame(session('status'), $stranger->getSession()->get('status'));
        $studentTry->assertRedirect('/staff/login');

        // Only the real staff account got an email.
        Mail::assertSent(OtpCodeMail::class, 1);
        Mail::assertSent(OtpCodeMail::class, fn ($mail) => $mail->hasTo($staff->email));
    }

    public function test_staff_can_sign_in_with_the_emailed_code_and_land_on_the_dashboard(): void
    {
        $staff = $this->staff('guidance');
        $code = OtpCode::generateForEmail($staff->email)['plain'];

        $this->post('/staff/login/verify', ['email' => $staff->email, 'code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/staff/login/verify', ['email' => $staff->email, 'code' => $code])->assertRedirect('/staff');
        $this->assertAuthenticatedAs($staff);
    }

    public function test_a_student_email_cannot_sign_in_through_the_staff_page_even_with_a_valid_code(): void
    {
        $student = $this->student();
        $code = OtpCode::generateForEmail($student->email)['plain'];

        $this->post('/staff/login/verify', ['email' => $student->email, 'code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_staff_create_command_makes_a_staff_account_and_refuses_bad_input(): void
    {
        $this->artisan('staff:create', ['email' => 'Counselor@fatima.edu.ph', 'role' => 'guidance', '--name' => 'Ms. Cruz'])->assertSuccessful();

        $user = User::where('email', 'counselor@fatima.edu.ph')->firstOrFail();
        $this->assertSame('guidance', $user->role);
        $this->assertSame('Ms. Cruz', $user->name);

        $this->artisan('staff:create', ['email' => 'counselor@fatima.edu.ph', 'role' => 'admin'])->assertFailed();
        $this->artisan('staff:create', ['email' => 'x@fatima.edu.ph', 'role' => 'student'])->assertFailed();
        $this->artisan('staff:create', ['email' => 'juan1234pam@student.fatima.edu.ph', 'role' => 'admin'])->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => 'juan1234pam@student.fatima.edu.ph']);
    }

    /* ---------- who can see what ---------- */

    public function test_students_and_signed_out_visitors_cannot_open_the_staff_area(): void
    {
        $this->get('/staff')->assertRedirect(route('staff.login'));
        $this->get('/staff/reports')->assertRedirect(route('staff.login'));

        $this->actingAs($this->student());
        foreach (['/staff', '/staff/reports', '/staff/sanctions', '/staff/activity'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_staff_are_kept_out_of_the_student_community(): void
    {
        $author = $this->student();
        $post = Post::create(['author_id' => $author->id, 'category' => 'Vent', 'body' => 'PRIVATE-VENT', 'is_anonymous' => true]);

        foreach (['admin', 'guidance', 'moderator'] as $role) {
            $staff = $this->staff($role);
            $this->actingAs($staff)->get('/feed')->assertRedirect(route('staff.dashboard'));
            $this->actingAs($staff)->get("/post/{$post->id}")->assertRedirect(route('staff.dashboard'));
            $this->actingAs($staff)->get('/search?q=PRIVATE')->assertRedirect(route('staff.dashboard'));
            $this->actingAs($staff)->post('/post', ['category' => 'Vent', 'body' => 'staff post'])->assertForbidden();
            $this->actingAs($staff)->get('/home')->assertRedirect(route('staff.dashboard'));
        }
    }

    public function test_staff_only_see_reported_content_never_unreported_posts(): void
    {
        $author = $this->student();
        Post::create(['author_id' => $author->id, 'category' => 'Vent', 'body' => 'NEVER-REPORTED-VENT', 'is_anonymous' => true]);
        $this->reportedPost($author, 'Sexual content', 'REPORTED-VENT');

        $staff = $this->staff();
        $this->actingAs($staff)->get('/staff/reports?view=all')->assertOk()->assertSee('REPORTED-VENT')->assertDontSee('NEVER-REPORTED-VENT');
        $this->actingAs($staff)->get('/staff')->assertOk()->assertDontSee('NEVER-REPORTED-VENT');
    }

    /* ---------- privacy ---------- */

    public function test_case_page_shows_the_alias_students_saw_and_hides_names_emails_ids_and_the_reporter(): void
    {
        $author = $this->student(['display_name' => 'Maria Santos']);
        $reporter = $this->student(['display_name' => 'Rita Reporter']);
        $post = Post::create(['author_id' => $author->id, 'category' => 'Vent', 'body' => 'BAD-WORDS-POST', 'is_anonymous' => true]);
        $this->actingAs($reporter)->post("/report/post/{$post->id}", ['category' => 'Profanity']);
        $report = Report::firstOrFail();

        foreach (['admin', 'guidance', 'moderator'] as $role) {
            $this->actingAs($this->staff($role))->get("/staff/reports/{$report->id}")->assertOk()
                ->assertSee('BAD-WORDS-POST')
                ->assertSee(Alias::for($author->id, $post->id))
                ->assertDontSee('Maria Santos')
                ->assertDontSee($author->email)
                ->assertDontSee($author->student_id)
                ->assertDontSee('Rita Reporter')
                ->assertDontSee($reporter->email);
        }
    }

    public function test_a_non_anonymous_post_shows_the_name_the_author_chose_to_show(): void
    {
        $author = $this->student(['display_name' => 'Maria Santos']);
        [, $report] = $this->reportedPost($author, 'Harassment', 'OPEN-POST', anonymous: false);

        $this->actingAs($this->staff())->get("/staff/reports/{$report->id}")->assertSee('Maria Santos')->assertDontSee($author->email);
    }

    public function test_reveal_needs_a_real_reason_is_logged_and_only_shows_to_the_person_who_revealed(): void
    {
        $author = $this->student();
        [, $report] = $this->reportedPost($author);
        $guidance = $this->staff('guidance');
        $admin = $this->staff('admin');

        $this->actingAs($guidance)->post("/staff/reports/{$report->id}/reveal", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($guidance)->post("/staff/reports/{$report->id}/reveal", ['reason' => 'too short'])->assertSessionHasErrors('report');
        $this->assertDatabaseMissing('mod_actions', ['action' => 'unmask']);

        $this->actingAs($guidance)->post("/staff/reports/{$report->id}/reveal", ['reason' => 'Student must be called in for a discipline meeting'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('mod_actions', [
            'report_id' => $report->id,
            'moderator_id' => $guidance->id,
            'target_user_id' => $author->id,
            'action' => 'unmask',
            'reason' => 'Student must be called in for a discipline meeting',
        ]);

        // The revealing staff member sees who it is, including the student ID.
        $this->actingAs($guidance)->get("/staff/reports/{$report->id}")->assertSee($author->email)->assertSee($author->student_id);
        // Another staff member does not, until they reveal it themselves.
        $this->actingAs($admin)->get("/staff/reports/{$report->id}")->assertDontSee($author->email);
        // The reveal shows in the audit log for everyone to see.
        $this->actingAs($admin)->get('/staff/activity')->assertSee('Identity revealed')->assertSee('Student must be called in');
    }

    public function test_a_revealed_identity_disappears_after_the_window(): void
    {
        $author = $this->student();
        [, $report] = $this->reportedPost($author);
        $guidance = $this->staff('guidance');

        $this->actingAs($guidance)->post("/staff/reports/{$report->id}/reveal", ['reason' => 'Safety follow-up with the student']);
        ModAction::where('action', 'unmask')->update(['created_at' => now()->subMinutes(31)]);

        $this->actingAs($guidance)->get("/staff/reports/{$report->id}")->assertDontSee($author->email);
    }

    public function test_moderators_and_students_cannot_reveal_identities(): void
    {
        $author = $this->student();
        [, $report] = $this->reportedPost($author);

        $this->actingAs($this->staff('moderator'))->post("/staff/reports/{$report->id}/reveal", ['reason' => 'I am curious who this is'])->assertForbidden();
        $this->actingAs($this->student())->post("/staff/reports/{$report->id}/reveal", ['reason' => 'I am curious who this is'])->assertForbidden();
        $this->assertDatabaseMissing('mod_actions', ['action' => 'unmask']);
    }

    /* ---------- decisions ---------- */

    public function test_guidance_and_admin_can_both_decide_on_a_case(): void
    {
        $author = $this->student();
        [$post, $report] = $this->reportedPost($author);
        $guidance = $this->staff('guidance');

        $this->actingAs($guidance)->post("/staff/reports/{$report->id}/act", ['action' => 'remove', 'reason' => 'Sexual content in a public post'])->assertSessionHasNoErrors();
        $this->assertNotNull($post->fresh()->removed_at);

        $this->actingAs($guidance)->post("/staff/reports/{$report->id}/act", ['action' => 'ban', 'reason' => 'Repeated sexual posts'])->assertSessionHasNoErrors();
        $this->assertNotNull($author->fresh()->banned_at);

        $this->actingAs($this->staff('admin'))->post("/staff/reports/{$report->id}/act", ['action' => 'unban', 'reason' => 'Appeal accepted'])->assertSessionHasNoErrors();
        $this->assertNull($author->fresh()->banned_at);
    }

    public function test_pressing_enter_in_the_reason_box_cannot_trigger_an_action(): void
    {
        $author = $this->student();
        [$post, $report] = $this->reportedPost($author);

        // The inert first button submits an empty action.
        $this->actingAs($this->staff())->post("/staff/reports/{$report->id}/act", ['action' => '', 'reason' => 'Enter pressed'])->assertSessionHasErrors('action');
        $this->assertNull($post->fresh()->removed_at);
    }

    public function test_mute_length_is_chosen_by_staff_and_limited_to_the_listed_choices(): void
    {
        $author = $this->student();
        [, $report] = $this->reportedPost($author);
        $admin = $this->staff();

        $this->actingAs($admin)->post("/staff/reports/{$report->id}/act", ['action' => 'mute', 'reason' => 'Cooling off period', 'mute_days' => 99])->assertSessionHasErrors('report');
        $this->assertNull($author->fresh()->muted_until);

        $this->actingAs($admin)->post("/staff/reports/{$report->id}/act", ['action' => 'mute', 'reason' => 'Cooling off period', 'mute_days' => 14])->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(now()->addDays(14)->timestamp, $author->fresh()->muted_until->timestamp, 5);

        // The muted student is told how long.
        $this->assertDatabaseHas('alerts', ['user_id' => $author->id, 'body' => 'You are muted for 14 days (you can read, but not post, comment, report or vote). Reason: Cooling off period']);
    }

    public function test_default_mute_is_still_seven_days(): void
    {
        $author = $this->student();
        [, $report] = $this->reportedPost($author);

        $this->actingAs($this->staff())->post("/staff/reports/{$report->id}/act", ['action' => 'mute', 'reason' => 'Cooling off period']);

        $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, $author->fresh()->muted_until->timestamp, 5);
    }

    public function test_the_suggestion_escalates_with_each_offence_but_staff_still_decide(): void
    {
        $author = $this->student();
        $admin = $this->staff();

        [$first, $firstReport] = $this->reportedPost($author, 'Profanity', 'FIRST');
        $this->actingAs($admin)->get("/staff/reports/{$firstReport->id}")->assertSee('Suggested: Warn')->assertSee('No earlier warnings');
        $this->actingAs($admin)->post("/staff/reports/{$firstReport->id}/act", ['action' => 'warn', 'reason' => 'First warning given']);

        [, $secondReport] = $this->reportedPost($author, 'Sexual content', 'SECOND');
        $this->actingAs($admin)->get("/staff/reports/{$secondReport->id}")->assertSee('Suggested: Mute')->assertSee('1 warning');
        $this->actingAs($admin)->post("/staff/reports/{$secondReport->id}/act", ['action' => 'mute', 'reason' => 'Second offence']);

        [, $thirdReport] = $this->reportedPost($author, 'Sexual content', 'THIRD');
        $this->actingAs($admin)->get("/staff/reports/{$thirdReport->id}")->assertSee('Suggested: Ban')->assertSee('1 warning, 1 mute');
    }

    public function test_self_harm_is_treated_as_a_student_who_needs_support_not_a_penalty(): void
    {
        $author = $this->student();
        [, $report] = $this->reportedPost($author, 'Self-harm', 'I cannot do this anymore');

        $this->actingAs($this->staff())->get("/staff/reports/{$report->id}")->assertSee('Suggested: Refer to Guidance')->assertSee('needs support');
    }

    public function test_comments_show_the_post_they_were_made_on(): void
    {
        $author = $this->student();
        $post = Post::create(['author_id' => $author->id, 'category' => 'Ask', 'body' => 'THE-ORIGINAL-POST', 'is_anonymous' => true]);
        $commenter = $this->student();
        $comment = Comment::create(['post_id' => $post->id, 'author_id' => $commenter->id, 'body' => 'THE-BAD-COMMENT', 'is_anonymous' => true]);
        $this->actingAs($this->student())->post("/report/comment/{$comment->id}", ['category' => 'Profanity']);
        $report = Report::firstOrFail();

        $this->actingAs($this->staff())->get("/staff/reports/{$report->id}")->assertOk()
            ->assertSee('THE-BAD-COMMENT')
            ->assertSee('THE-ORIGINAL-POST')
            ->assertSee(Alias::for($commenter->id, $post->id))
            ->assertDontSee($commenter->email);
    }

    /* ---------- lists ---------- */

    public function test_new_report_reasons_exist_and_count_as_high_severity(): void
    {
        $this->assertContains('Sexual content', Report::CATEGORIES);
        $this->assertContains('Profanity', Report::CATEGORIES);
        $this->assertSame('high', Report::severityFor('Sexual content'));
        $this->assertSame('high', Report::severityFor('Profanity'));

        $author = $this->student();
        $post = Post::create(['author_id' => $author->id, 'category' => 'Vent', 'body' => 'x post', 'is_anonymous' => true]);
        $this->actingAs($this->student())->post("/report/post/{$post->id}", ['category' => 'Sexual content'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reports', ['reportable_id' => $post->id, 'category' => 'Sexual content']);

        // Students see the new reasons in the report sheet and the rules page.
        $this->actingAs($this->student())->get('/rules')->assertSee('Sexual content')->assertSee('Profanity');
    }

    public function test_report_tabs_and_filters_sort_cases_into_the_right_queues(): void
    {
        $author = $this->student();
        [, $critical] = $this->reportedPost($author, 'Threats', 'CRITICAL-ONE');          // critical: straight to staff
        [, $voting] = $this->reportedPost($author, 'Profanity', 'STILL-VOTING');           // high: community votes first
        $critical->update(['referred_guidance_at' => now()]);
        $staff = $this->staff();

        $this->actingAs($staff)->get('/staff/reports')->assertSee('CRITICAL-ONE')->assertDontSee('STILL-VOTING');
        $this->actingAs($staff)->get('/staff/reports?view=voting')->assertSee('STILL-VOTING')->assertDontSee('CRITICAL-ONE');
        $this->actingAs($staff)->get('/staff/reports?view=referred')->assertSee('CRITICAL-ONE');
        $this->actingAs($staff)->get('/staff/reports?view=all&severity=high')->assertSee('STILL-VOTING')->assertDontSee('CRITICAL-ONE');
        $this->actingAs($staff)->get('/staff/reports?view=all&type=comment')->assertDontSee('STILL-VOTING');

        // Guidance opens on the cases referred to them.
        $this->actingAs($this->staff('guidance'))->get('/staff/reports')->assertSee('CRITICAL-ONE');
    }

    public function test_overview_summarises_the_queue_in_plain_words(): void
    {
        $this->actingAs($this->staff())->get('/staff')->assertOk()->assertSee('Nothing is waiting for a decision.');

        $author = $this->student();
        $this->reportedPost($author, 'Threats', 'WAITING-ONE');
        $this->actingAs($this->staff())->get('/staff')->assertOk()->assertSee('1 report needs a decision. 1 is critical.')->assertSee('WAITING-ONE');
    }

    public function test_restricted_accounts_are_listed_by_number_not_name(): void
    {
        $muted = $this->student(['display_name' => 'Mia Muted']);
        $banned = $this->student(['display_name' => 'Ben Banned']);
        $muted->forceFill(['muted_until' => now()->addDays(3)])->save();
        $banned->forceFill(['banned_at' => now()])->save();

        $this->actingAs($this->staff('guidance'))->get('/staff/sanctions')->assertOk()
            ->assertSee('Account '.str_pad((string) $muted->id, 4, '0', STR_PAD_LEFT))
            ->assertSee('Account '.str_pad((string) $banned->id, 4, '0', STR_PAD_LEFT))
            ->assertDontSee('Mia Muted')->assertDontSee('Ben Banned')
            ->assertDontSee($muted->email)->assertDontSee($banned->email);
    }

    public function test_staff_times_are_shown_in_philippine_time(): void
    {
        $author = $this->student();
        [$post, $report] = $this->reportedPost($author);
        $post->forceFill(['created_at' => '2026-10-20 06:30:00'])->save();   // 06:30 UTC = 2:30 PM in Manila

        $this->actingAs($this->staff())->get("/staff/reports/{$report->id}")->assertSee('Oct 20, 2:30 PM');
    }

    public function test_staff_can_sign_out(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)->post('/staff/logout')->assertRedirect(route('staff.login'));
        $this->assertGuest();
    }

    /* ---------- local-only password sign-in ---------- */

    public function test_password_sign_in_works_only_in_the_local_environment(): void
    {
        $this->app['env'] = 'local';
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);   // CSRF is only skipped when env is "testing"
        (new \Database\Seeders\StaffDemoSeeder())->run();
        $admin = User::where('email', 'mqdavid0738pam@admin.fatima.edu.ph')->firstOrFail();
        $this->assertSame('admin', $admin->role);

        $this->get('/staff/login')->assertSee('Sign in with password');
        $this->post('/staff/login/password', ['email' => $admin->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/staff/login/password', ['email' => $admin->email, 'password' => 'admin123'])->assertRedirect('/staff');
        $this->assertAuthenticatedAs($admin);

        // Anywhere else it does not exist: no form, no route, no seeded accounts.
        $this->post('/staff/logout');
        $this->app['env'] = 'production';
        $this->get('/staff/login')->assertDontSee('Sign in with password');
        $this->post('/staff/login/password', ['email' => $admin->email, 'password' => 'admin123'])->assertNotFound();
        $this->assertGuest();
    }

    public function test_demo_seeder_refuses_to_run_outside_local(): void
    {
        $this->app['env'] = 'production';
        (new \Database\Seeders\StaffDemoSeeder())->run();

        $this->assertDatabaseMissing('users', ['email' => 'mqdavid0738pam@admin.fatima.edu.ph']);
    }

    public function test_a_student_cannot_use_the_password_sign_in(): void
    {
        $this->app['env'] = 'local';
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $student = $this->student();
        $student->forceFill(['password' => \Illuminate\Support\Facades\Hash::make('secret123')])->save();

        $this->post('/staff/login/password', ['email' => $student->email, 'password' => 'secret123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
