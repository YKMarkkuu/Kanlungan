<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Likes (posts + comments) and "Helpful" (comments only) in one table.
     * One row per user per thing per type, enforced by the unique index.
     */
    public function up(): void
    {
        Schema::create('reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reactable_type');
            $table->unsignedBigInteger('reactable_id');
            $table->string('type', 10); // like | helpful
            $table->timestamps();

            $table->unique(['user_id', 'reactable_type', 'reactable_id', 'type'], 'reactions_unique');
            $table->index(['reactable_type', 'reactable_id'], 'reactions_target');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reactions');
    }
};
