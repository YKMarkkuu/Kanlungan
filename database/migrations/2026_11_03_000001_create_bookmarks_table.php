<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** One row = one student saving one post. Private to the saver; never shown to peers. */
    public function up(): void
    {
        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'post_id'], 'bookmarks_unique_pair');
            $table->index(['user_id', 'created_at'], 'bookmarks_user_recent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookmarks');
    }
};
