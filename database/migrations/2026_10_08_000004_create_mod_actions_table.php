<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Audit log: every moderator / guidance action with the reason they had to give. */
    public function up(): void
    {
        Schema::create('mod_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_id')->nullable();
            $table->foreignId('moderator_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('target_user_id')->nullable();
            $table->string('action', 12);
            $table->text('reason');
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mod_actions');
    }
};
