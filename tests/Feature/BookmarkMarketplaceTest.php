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

    public function test_marketplace_requires_items_and_a_display_name(): void
    {
        $seller = User::factory()->create(['display_name' => null]);

        $this->actingAs($seller)->postJson(route('post.store'), [
            'category' => Post::MARKETPLACE,
            'body' => 'Selling a calculator',
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->actingAs($seller)->postJson(route('post.store'), [
            'category' => Post::MARKETPLACE,
            'body' => 'Selling a calculator',
            'items' => [['price' => '350']],
        ])->assertUnprocessable()->assertJsonValidationErrors('display_name');
        $this->assertSame(0, Post::count());
    }

    public function test_single_item_listing_is_forced_non_anonymous_and_has_no_item_rows(): void
    {
        $seller = User::factory()->create(['display_name' => 'Seller Name']);

        $this->actingAs($seller)->postJson(route('post.store'), [
            'category' => Post::MARKETPLACE,
            'body' => 'Scientific calculator',
            'anonymous' => '1',
            'items' => [['label' => 'ignored for a single item', 'price' => '350']],
            'condition' => 'Like New',
        ])->assertCreated();

        $post = Post::where('author_id', $seller->id)->firstOrFail();
        $this->assertSame(350, $post->price);
        $this->assertSame('Like New', $post->condition);
        $this->assertFalse($post->is_anonymous);
        $this->assertSame(0, $post->items()->count());
        $this->actingAs($seller)->get(route('feed'))
            ->assertOk()
            ->assertSee('Seller Name')
            ->assertSee('₱350')
            ->assertSee('Like New')
            ->assertSee('data-bookmark', false);
    }

    public function test_multi_item_listing_stores_items_and_shows_range_then_itemized_thread(): void
    {
        $seller = User::factory()->create(['display_name' => 'Seller Name']);

        $this->actingAs($seller)->postJson(route('post.store'), [
            'category' => Post::MARKETPLACE,
            'body' => 'Dorm clearance sale',
            'items' => [
                ['label' => 'Clip-on lamp', 'price' => '300'],
                ['label' => 'Desk fan', 'price' => '250'],
            ],
        ])->assertCreated();

        $post = Post::where('author_id', $seller->id)->firstOrFail();
        $this->assertSame(250, $post->price);
        $this->assertSame(['Clip-on lamp', 'Desk fan'], $post->items->pluck('label')->all());

        $this->actingAs($seller)->get(route('feed'))->assertOk()
            ->assertSee('₱250 – ₱300')
            ->assertSee('2 items');
        $this->actingAs($seller)->get(route('post.show', $post->id))->assertOk()
            ->assertSee('cm-items__row', false)
            ->assertSee('Clip-on lamp')
            ->assertSee('Desk fan');
    }

    public function test_multi_item_listing_requires_a_name_for_each_item_and_has_a_five_item_limit(): void
    {
        $seller = User::factory()->create(['display_name' => 'Seller Name']);

        $this->actingAs($seller)->postJson(route('post.store'), [
            'category' => Post::MARKETPLACE,
            'body' => 'Two things',
            'items' => [['label' => '', 'price' => '100'], ['label' => 'Fan', 'price' => '200']],
        ])->assertUnprocessable()->assertJsonValidationErrors('items.0.label');

        $sixItems = array_map(fn ($number) => ['label' => "Item {$number}", 'price' => '10'], range(1, 6));
        $this->actingAs($seller)->postJson(route('post.store'), [
            'category' => Post::MARKETPLACE,
            'body' => 'Too many',
            'items' => $sixItems,
        ])->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertSame(0, Post::count());
    }

    public function test_item_prices_and_condition_are_validated_and_items_are_ignored_for_other_categories(): void
    {
        $seller = User::factory()->create(['display_name' => 'Seller Name']);

        $this->actingAs($seller)->postJson(route('post.store'), [
            'category' => Post::MARKETPLACE,
            'body' => 'Invalid listing',
            'items' => [['price' => '-1']],
            'condition' => 'Unknown',
        ])->assertUnprocessable()->assertJsonValidationErrors(['items.0.price', 'condition']);

        $this->actingAs($seller)->postJson(route('post.store'), [
            'category' => Post::MEME,
            'body' => 'A regular post',
            'items' => [['label' => 'Stray item', 'price' => '25']],
            'condition' => 'Used',
            'anonymous' => '1',
        ])->assertCreated();

        $post = Post::where('category', Post::MEME)->firstOrFail();
        $this->assertNull($post->price);
        $this->assertNull($post->condition);
        $this->assertSame(0, $post->items()->count());
        $this->assertTrue($post->is_anonymous);
    }

    public function test_create_page_exposes_marketplace_form_fields_and_display_name_state(): void
    {
        $seller = User::factory()->create(['display_name' => null]);

        $this->actingAs($seller)->get(route('post.create'))
            ->assertOk()
            ->assertSee('Marketplace')
            ->assertSee('id="market-fields"', false)
            ->assertSee('data-item-price', false)
            ->assertSee('name="items[0][price]"', false)
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
