<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookmarkMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_bookmarks_toggle_and_saved_posts_are_private_to_the_viewer(): void
    {
        [$author, $viewer, $other] = User::factory()->count(3)->create();
        $post = Post::create([
            'author_id' => $author->id,
            'category' => 'Ask',
            'body' => 'A post to save',
            'is_anonymous' => true,
        ]);

        $this->actingAs($viewer)->postJson(route('post.bookmark', $post->id))
            ->assertOk()
            ->assertJson(['status' => 'success', 'is_active' => true]);
        $this->assertDatabaseHas('bookmarks', ['user_id' => $viewer->id, 'post_id' => $post->id]);
        $this->actingAs($viewer)->get(route('community.me.saved'))
            ->assertOk()
            ->assertSee('A post to save')
            ->assertSee('Only you can see your saved posts.');
        $this->actingAs($other)->get(route('community.me.saved'))
            ->assertOk()
            ->assertDontSee('A post to save');

        $this->actingAs($viewer)->postJson(route('post.bookmark', $post->id))
            ->assertOk()
            ->assertJson(['status' => 'success', 'is_active' => false]);
        $this->assertDatabaseMissing('bookmarks', ['user_id' => $viewer->id, 'post_id' => $post->id]);
    }

    public function test_removed_posts_cannot_be_saved_but_existing_bookmarks_can_be_removed(): void
    {
        [$author, $viewer] = User::factory()->count(2)->create();
        $post = Post::create([
            'author_id' => $author->id,
            'category' => 'Share',
            'body' => 'A removed post',
            'is_anonymous' => true,
            'removed_at' => now(),
            'removed_by' => 'moderator',
        ]);
        Bookmark::create(['user_id' => $viewer->id, 'post_id' => $post->id]);

        $this->actingAs($viewer)->postJson(route('post.bookmark', $post->id))
            ->assertOk()
            ->assertJson(['status' => 'success', 'is_active' => false]);
        $this->assertDatabaseMissing('bookmarks', ['user_id' => $viewer->id, 'post_id' => $post->id]);

        $this->actingAs($viewer)->postJson(route('post.bookmark', $post->id))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['report']);
    }

    public function test_marketplace_requires_display_name_and_stores_non_anonymous_listing_data(): void
    {
        $seller = User::factory()->create(['display_name' => null]);

        $this->actingAs($seller)->postJson(route('post.store'), [
            'category' => Post::MARKETPLACE,
            'body' => 'A free used textbook',
            'price' => '0',
            'condition' => 'Used',
            'anonymous' => '1',
        ])->assertUnprocessable()->assertJsonValidationErrors(['display_name']);
        $this->assertDatabaseMissing('posts', ['author_id' => $seller->id]);

        $seller->forceFill(['display_name' => 'Seller Name'])->save();
        $this->actingAs($seller)->postJson(route('post.store'), [
            'category' => Post::MARKETPLACE,
            'body' => 'A free used textbook',
            'price' => '0',
            'condition' => 'Used',
            'anonymous' => '1',
        ])->assertCreated()->assertJsonPath('status', 'success');

        $post = Post::where('author_id', $seller->id)->firstOrFail();
        $this->assertSame(0, $post->price);
        $this->assertSame('Used', $post->condition);
        $this->assertFalse($post->is_anonymous);
        $this->actingAs($seller)->get(route('feed'))
            ->assertOk()
            ->assertSee('Seller Name')
            ->assertSee('Free')
            ->assertSee('Used')
            ->assertSee('data-bookmark', false);
    }

    public function test_marketplace_price_and_condition_are_validated_and_non_market_posts_ignore_them(): void
    {
        $seller = User::factory()->create(['display_name' => 'Seller Name']);

        $this->actingAs($seller)->postJson(route('post.store'), [
            'category' => Post::MARKETPLACE,
            'body' => 'Invalid listing',
            'price' => '-1',
            'condition' => 'Unknown',
        ])->assertUnprocessable()->assertJsonValidationErrors(['price', 'condition']);

        $this->actingAs($seller)->postJson(route('post.store'), [
            'category' => 'Share',
            'body' => 'A regular post',
            'price' => '25',
            'condition' => 'Used',
            'anonymous' => '1',
        ])->assertCreated();

        $post = Post::where('category', 'Share')->firstOrFail();
        $this->assertNull($post->price);
        $this->assertNull($post->condition);
        $this->assertTrue($post->is_anonymous);
    }

    public function test_create_page_exposes_marketplace_form_fields_and_display_name_state(): void
    {
        $seller = User::factory()->create(['display_name' => null]);

        $this->actingAs($seller)->get(route('post.create'))
            ->assertOk()
            ->assertSee('Marketplace')
            ->assertSee('id="market-fields"', false)
            ->assertSee('id="price"', false)
            ->assertSee('data-has-name="0"', false);
    }

    public function test_marketplace_reactions_do_not_award_or_deduct_community_points(): void
    {
        [$seller, $viewer] = User::factory()->count(2)->create();
        $post = Post::create([
            'author_id' => $seller->id,
            'category' => Post::MARKETPLACE,
            'body' => 'A listing',
            'price' => 100,
            'is_anonymous' => false,
        ]);

        $this->actingAs($viewer)->postJson(route('post.like', $post->id))
            ->assertOk()
            ->assertJson(['status' => 'success', 'is_active' => true]);
        $this->assertSame(1, $post->reactions()->where('type', Reaction::LIKE)->count());
        $this->assertSame(0, $seller->fresh()->points);

        $this->actingAs($viewer)->postJson(route('post.like', $post->id))
            ->assertOk()
            ->assertJson(['status' => 'success', 'is_active' => false]);
        $this->assertSame(0, $seller->fresh()->points);
    }
}
