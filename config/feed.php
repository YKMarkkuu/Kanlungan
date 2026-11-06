<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Smart feed ranking
    |--------------------------------------------------------------------------
    |
    | score = (base_score + engagement_points) / (hours_old + age_offset_hours) ^ gravity
    |
    | Tune these here; nothing else needs a code change. If you change base_score,
    | gravity or age_offset_hours, run `php artisan feed:rescore` to apply it now.
    |
    */

    'base_score' => 10,

    'gravity' => 1.2,

    'age_offset_hours' => 2,

    /** Posts older than this stop being ranked (score 0) and are listed newest-first after the ranked ones. */
    'window_days' => 14,

    'weights' => [
        'like' => 1,
        'comment' => 3,      // per distinct commenter (not per comment), author excluded
        'helpful' => 5,      // helpful marks on the post's comments; Ask / Help / Academic only
        'accepted' => 50,    // post has an accepted answer; Ask / Help / Academic only
    ],

    /** Per-signal ceilings, so one runaway thread cannot dominate the campus feed. */
    'caps' => [
        'likes' => 50,
        'commenters' => 20,
        'helpful' => 20,
    ],

    /** Posts with an open / voting / escalated report are not amplified. */
    'reported_factor' => 0.25,

    /** The same author may not appear again within this many slots (when other authors are available). */
    'diversity_lookback' => 2,

    /** The viewer's own posts from the last N minutes are pinned to the top of their feed. */
    'pin_own_minutes' => 10,

    'snapshot' => [
        'size' => 150,       // ranked candidates frozen per feed load
        'per_page' => 15,
        'ttl_minutes' => 30, // how long "Load more" can keep paging the same frozen order
    ],

];
