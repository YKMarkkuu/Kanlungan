<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * engagement_points: weighted likes + commenters + helpful marks + accepted answer (see config/feed.php).
     *                    Recomputed from scratch for one post whenever its engagement changes.
     * feed_score:        (base + engagement_points) / (hours_old + 2) ^ 1.5, refreshed by `feed:rescore`.
     *                    0 = outside the ranked window (or removed), so those posts fall back to newest-first.
     *
     * After migrating, run `php artisan feed:rescore --rebuild` once to backfill existing posts
     * (start.sh does this on every deploy; it is idempotent).
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->unsignedInteger('engagement_points')->default(0);
            $table->double('feed_score')->default(0);

            $table->index(['feed_score', 'created_at', 'id'], 'posts_feed_rank');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_feed_rank');
            $table->dropColumn(['engagement_points', 'feed_score']);
        });
    }
};
