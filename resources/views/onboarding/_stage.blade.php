{{--
    Onboarding sandbox. Renders the REAL components (post-card, comment-card, alert-item, report-banner)
    with fixture arrays that follow the docs/FRONTEND.md data contract, so the tour is 1:1 with the app.

    Rules:
      - Everything is inert (.ob-stage is pointer-events:none + inert) and driven by the ghost hand in community.js.
      - Fixture ids are 9xxx and every sandbox-only id/data attribute is ob-* / data-ob-*, so nothing here
        can collide with the real bindings (#post-form, #comment-form, #report-sheet, #feed-posts ...).
      - The real create-post / comment-box / report-sheet pages are NOT touched (tests assert their exact markup);
        their visual shells are mirrored below with the same cm-* classes.

    Expects $scene: feed | thread | compose | market | report
--}}
@php
    $base = ['feed' => 9000, 'thread' => 9100, 'compose' => 9200, 'market' => 9300, 'report' => 9400][$scene];
    $alias = 'Anonymous Falcon #4821';
    $owl = 'Anonymous Owl #1093';

    $mkPost = fn (array $o = []) => array_merge([
        'id' => $base + 1, 'avatar_path' => null, 'author_alias' => $alias, 'is_anonymous' => true,
        'category' => 'Ask', 'created_at' => now()->subMinutes(5), 'time_ago' => '5m',
        'body' => 'How do I become a student assistant? Is there a form, or do I ask the professor directly?',
        'images' => [], 'has_bookmarked' => false, 'is_marketplace' => false, 'is_meme' => false,
        'price' => null, 'condition' => null, 'items' => [], 'item_count' => 0,
        'price_text' => null, 'price_label' => null,
        'removed' => false, 'removed_by' => null, 'mine' => false, 'report' => null,
        'likes' => 12, 'comments' => 4, 'liked' => false,
    ], $o);

    $mkComment = fn (array $o = []) => array_merge([
        'id' => $base + 11, 'post_id' => $base + 1, 'parent_id' => null, 'avatar_path' => null,
        'created_at' => now()->subMinutes(3), 'time_ago' => '3m', 'author_alias' => $owl,
        'is_anonymous' => true, 'is_op' => false,
        'body' => 'Try checking the handbook, then ask your department office.',
        'removed' => false, 'mine' => false, 'report' => null,
        'likes' => 2, 'liked' => false, 'helpful_count' => 0, 'helpful_marked' => false,
        'is_accepted' => false, 'replies' => [],
    ], $o);
@endphp

@if ($scene === 'feed')
    {{-- Scene 1: Like, then open the thread from the comment icon. --}}
    @php
        $feedPost = $mkPost();
        $threadPost = $mkPost(['id' => $base + 2, 'likes' => 13, 'liked' => true]);
        $threadComment = $mkComment(['id' => $base + 12, 'post_id' => $base + 2]);
    @endphp
    <div class="ob-phone">
        <section class="ob-view is-active" data-ob-view="feed">
            <div class="ob-screen">
                <nav class="cm-chips" aria-hidden="true">
                    <span class="chip chip--active">All</span>
                    @foreach (\App\Models\Post::CATEGORIES as $category)
                        <span class="chip">{{ $category }}</span>
                    @endforeach
                </nav>
                <div class="cm-feed-list">
                    @include('components.post-card', ['post' => $feedPost, 'link' => true, 'showReportStatus' => false])
                </div>
            </div>
        </section>

        <section class="ob-view" data-ob-view="thread">
            <div class="ob-screen">
                <header class="cm-header"><h1>Post</h1></header>
                @include('components.post-card', ['post' => $threadPost, 'link' => false, 'showReportStatus' => false])
                <div class="cm-comment-list">
                    @include('components.comment-card', ['comment' => $threadComment, 'reply' => false, 'canAccept' => false, 'locked' => false])
                </div>
            </div>
        </section>
    </div>

@elseif ($scene === 'thread')
    {{-- Scene 2: Helpful (star + points), then Reply (mention lands in the comment box). --}}
    @php
        $threadPost = $mkPost();
        $reply = $mkComment([
            'id' => $base + 13, 'parent_id' => $base + 11, 'author_alias' => $alias, 'is_op' => true,
            'body' => 'Thank you! I will do that tomorrow.', 'time_ago' => '1m', 'likes' => 0,
            'created_at' => now()->subMinute(),
        ]);
        $topComment = $mkComment(['replies' => [$reply]]);
    @endphp
    <div class="ob-phone">
        <p class="ob-points"><span id="onboarding-points-count">0</span> points</p>

        <section class="ob-view is-active" data-ob-view="thread">
            <div class="ob-scroll" data-ob-scroll>
                <div class="ob-screen ob-screen--composer">
                    <header class="cm-header"><h1>Post</h1></header>
                    @include('components.post-card', ['post' => $threadPost, 'link' => false, 'showReportStatus' => false])
                    <div class="cm-comment-list">
                        @include('components.comment-card', ['comment' => $topComment, 'reply' => false, 'canAccept' => false, 'locked' => false])
                    </div>
                </div>
            </div>

            {{-- Same shell as the comment box in feed/show.blade.php, minus the real ids. --}}
            <div class="cm-composer" data-ob-composer>
                <p class="cm-composer__reply" data-ob-reply-note hidden>
                    <span class="cm-composer__reply-text" data-ob-reply-text></span>
                    <span class="cm-icon-btn cm-composer__reply-cancel">@include('components.icon', ['name' => 'close'])</span>
                </p>
                <p class="cm-composer__label">Commenting anonymously as {{ $alias }}</p>
                <div class="cm-composer__row">
                    <span class="cm-icon-btn cm-anon-toggle is-on">@include('components.icon', ['name' => 'mask'])</span>
                    <textarea class="input" data-ob-comment-body rows="1" readonly tabindex="-1" placeholder="Write a comment…"></textarea>
                    <span class="btn btn--primary cm-composer__send">@include('components.icon', ['name' => 'send'])</span>
                </div>
            </div>
        </section>
    </div>
    <span class="onboarding__points-toast" id="onboarding-points-toast" aria-hidden="true">+3 points</span>

@elseif ($scene === 'compose')
    {{-- Scene 3: category, text, Anonymous (avatar becomes the mask), Post. --}}
    <div class="ob-phone">
        <section class="ob-view is-active" data-ob-view="compose">
            {{-- Same shell as feed/create.blade.php, with ob-* names so community.js never binds it. --}}
            <div class="cm-compose-form" data-ob-compose>
                <header class="cm-header">
                    <span class="cm-icon-btn">@include('components.icon', ['name' => 'close'])</span>
                    <h1 class="cm-header__title">New post</h1>
                    <span class="cm-icon-btn cm-compose__spacer" aria-hidden="true"></span>
                </header>

                <div class="cm-chips cm-chips--compose" role="radiogroup" aria-label="Category" data-ob-chips>
                    @foreach (\App\Models\Post::CATEGORIES as $category)
                        <label data-ob-chip="{{ $category }}">
                            <input type="radio" name="ob-category" value="{{ $category }}" class="cm-visually-hidden" tabindex="-1" @checked($category === 'Share')>
                            <span class="chip">{{ $category }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="cm-compose cm-compose--canvas">
                    <div class="cm-compose__row" data-ob-avatar-row>
                        <x-avatar :avatar-path="null" :name="null" class="cm-compose__avatar cm-compose__avatar--user" />
                        <span class="cm-avatar cm-compose__avatar cm-compose__avatar--anonymous" aria-hidden="true">
                            @include('components.icon', ['name' => 'mask'])
                        </span>
                        <div class="cm-compose__main">
                            <p class="text-small text-muted cm-compose__as" data-ob-anon-hint>Posting as Mark</p>
                            <textarea class="cm-compose__input" data-ob-body rows="3" readonly tabindex="-1" placeholder="What's on your mind?"></textarea>
                        </div>
                    </div>
                </div>

                <div class="cm-compose__bar">
                    <div class="cm-compose__tools">
                        <span class="cm-icon-btn cm-compose__attach">@include('components.icon', ['name' => 'clip'])</span>
                        <label class="cm-anon-badge" data-ob-anon>
                            <input class="cm-visually-hidden" type="checkbox" tabindex="-1">
                            <span class="cm-anon-badge__pill">
                                @include('components.icon', ['name' => 'mask'])
                                <span>Anonymous</span>
                            </span>
                        </label>
                    </div>
                    <span class="cm-compose__count text-small text-muted" data-ob-count>0 / 2000</span>
                    <button type="button" class="btn btn--primary cm-compose__post" data-ob-post tabindex="-1" disabled>Post</button>
                </div>
            </div>

            {{-- Real toast markup (.cm-toast) in a sandbox slot instead of the fixed body-level stack. --}}
            <div class="ob-toast" data-ob-toast hidden>
                <div class="cm-toast cm-toast--success">
                    <span class="cm-toast__icon" aria-hidden="true">@include('components.icon', ['name' => 'check'])</span>
                    <span class="cm-toast__text">Posted.</span>
                </div>
            </div>
        </section>
    </div>

@elseif ($scene === 'market')
    {{-- Scene 4: price badge, itemized list on the thread, save ribbon. --}}
    @php
        $items = [
            ['label' => 'Clip-on lamp', 'price' => 300],
            ['label' => 'Desk fan', 'price' => 250],
            ['label' => 'Scientific calculator', 'price' => 350],
        ];
        $market = [
            'category' => 'Marketplace', 'is_marketplace' => true, 'is_anonymous' => false,
            'author_alias' => 'Mika R.', 'time_ago' => '12m', 'created_at' => now()->subMinutes(12),
            'body' => 'Dorm clearance sale. Everything works. Pickup near the main gate.',
            'price' => 250, 'condition' => 'Like New', 'items' => $items, 'item_count' => count($items),
            'price_text' => \App\Services\CommunityService::priceText(array_column($items, 'price')),
            'price_label' => count($items).' items: '.implode(', ', array_map(
                fn ($i) => $i['label'].' '.number_format($i['price']).' pesos',
                $items
            )),
            'likes' => 4, 'comments' => 2,
        ];
        $listed = $mkPost($market);
        $detail = $mkPost(array_merge($market, ['id' => $base + 2]));
    @endphp
    <div class="ob-phone">
        <section class="ob-view is-active" data-ob-view="feed">
            <div class="ob-screen">
                <div class="cm-feed-list">
                    @include('components.post-card', ['post' => $listed, 'link' => true, 'showReportStatus' => false])
                </div>
            </div>
        </section>
        <section class="ob-view" data-ob-view="detail">
            <div class="ob-screen">
                <header class="cm-header"><h1>Post</h1></header>
                @include('components.post-card', ['post' => $detail, 'link' => false, 'showReportStatus' => false])
            </div>
        </section>
    </div>

@elseif ($scene === 'report')
    {{-- Scene 5: report sheet. Self-harm bypasses the vote; Spam goes to a community vote. --}}
    @php
        $reportBody = 'Everyone deserves to feel safe here.';
        $plainPost = $mkPost(['category' => 'Share', 'body' => $reportBody, 'likes' => 3, 'comments' => 1]);
        $votedPost = $mkPost([
            'id' => $base + 2, 'category' => 'Share', 'body' => $reportBody, 'likes' => 3, 'comments' => 1,
            'report' => [
                'id' => $base + 99, 'status' => 'voting', 'category' => 'Spam', 'votes' => 3, 'quorum' => 5,
                'ends_in' => '23h', 'is_reporter' => false, 'is_author' => false, 'has_voted' => false,
                'can_start' => false, 'can_vote' => true,
            ],
        ]);
        $reviewAlert = [
            'id' => $base + 50, 'kind' => 'report', 'icon' => 'flag', 'unread' => true, 'time' => 'Just now',
            'title' => 'Your report is under moderator review', 'snippet' => $reportBody,
            'body' => 'A moderator will review this report directly. It will not be sent to a community vote.',
        ];
    @endphp
    <div class="ob-phone ob-phone--report">
        <section class="ob-view is-active" data-ob-view="post">
            <div class="ob-screen">
                <header class="cm-header"><h1>Post</h1></header>
                @include('components.post-card', ['post' => $plainPost, 'link' => false, 'showReportStatus' => false])
            </div>
        </section>

        <section class="ob-view" data-ob-view="alert">
            <div class="ob-screen">
                <header class="cm-header"><h1>Activity</h1></header>
                <div class="cm-alert-list">
                    @include('components.alert-item', ['alert' => $reviewAlert])
                </div>
            </div>
        </section>

        <section class="ob-view" data-ob-view="vote">
            <div class="ob-screen">
                <header class="cm-header"><h1>Activity</h1></header>
                @include('components.post-card', ['post' => $votedPost, 'link' => false, 'showReportStatus' => true])
            </div>
        </section>

        {{-- Same shell and 8 real reasons as components/report-sheet.blade.php (no real ids, no form). --}}
        <div class="cm-sheet" data-ob-sheet hidden>
            <div class="cm-sheet__backdrop"></div>
            <div class="cm-sheet__panel">
                <h2 class="cm-sheet__title">Report this post</h2>
                <fieldset class="cm-sheet__reasons cm-fieldset">
                    @foreach (\App\Models\Report::CATEGORIES as $category)
                        <label class="cm-sheet__reason" data-ob-reason="{{ $category }}">
                            <input type="radio" name="ob-reason" value="{{ $category }}" tabindex="-1">
                            <span>{{ $category }}</span>
                        </label>
                    @endforeach
                </fieldset>
                <button type="button" class="btn btn--primary btn--block" data-ob-send tabindex="-1">Send report</button>
                <p class="text-small text-muted cm-sheet__note">Others can't see that you reported.</p>
            </div>
        </div>
    </div>
    <p class="onboarding__demo-caption" aria-live="polite">
        <span data-demo-text>Tap the flag on any post or comment.</span>
    </p>
@endif
