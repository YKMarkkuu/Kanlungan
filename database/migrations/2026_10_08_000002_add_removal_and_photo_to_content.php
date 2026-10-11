<?php

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * removed_at / removed_by ('community' | 'moderator'): the post or comment is hidden behind a notice.
     * image_path: optional photo on a post (re-encoded on upload, so no EXIF / location data is kept).
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->timestamp('removed_at')->nullable();
            $table->string('removed_by', 12)->nullable();
            $table->string('image_path')->nullable();
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->timestamp('removed_at')->nullable();
            $table->string('removed_by', 12)->nullable();
        });

        // Content already removed by an earlier vote (older version) stays removed.
        foreach (DB::table('reports')->where('outcome', 'upheld')->get() as $report) {
            $table = $report->reportable_type === Post::class ? 'posts' : ($report->reportable_type === Comment::class ? 'comments' : null);

            if ($table !== null) {
                DB::table($table)->where('id', $report->reportable_id)->whereNull('removed_at')
                    ->update(['removed_at' => now(), 'removed_by' => 'community']);
            }
        }
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['removed_at', 'removed_by', 'image_path']);
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn(['removed_at', 'removed_by']);
        });
    }
};
