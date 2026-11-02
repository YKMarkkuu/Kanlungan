<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Copies posts.image_path into post_images (position 0), then drops the column.
     * Removed posts are skipped: their files were already deleted.
     * insertOrIgnore + the unique (post_id, position) index make a re-run after a
     * partial failure safe (MySQL DDL is not transactional).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('posts', 'image_path')) {
            return;
        }

        DB::table('posts')
            ->whereNotNull('image_path')
            ->whereNull('removed_at')
            ->chunkById(200, function ($posts) {
                $rows = [];

                foreach ($posts as $post) {
                    $file = public_path($post->image_path);
                    $size = is_file($file) ? @getimagesize($file) : false;

                    $rows[] = [
                        'post_id' => $post->id,
                        'path' => $post->image_path,
                        'position' => 0,
                        'width' => $size[0] ?? null,
                        'height' => $size[1] ?? null,
                    ];
                }

                DB::table('post_images')->insertOrIgnore($rows);
            });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }

    /** Restores the column and copies each post's cover photo back. Photos 2 and 3 are lost on rollback. */
    public function down(): void
    {
        if (! Schema::hasColumn('posts', 'image_path')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->string('image_path')->nullable();
            });
        }

        DB::table('post_images')->where('position', 0)->orderBy('id')->chunkById(200, function ($images) {
            foreach ($images as $image) {
                DB::table('posts')->where('id', $image->post_id)->update(['image_path' => $image->path]);
            }
        });
    }
};
