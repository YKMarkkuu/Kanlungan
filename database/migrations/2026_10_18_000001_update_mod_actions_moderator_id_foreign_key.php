<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mod_actions', function (Blueprint $table) {
            $table->dropForeign(['moderator_id']);
        });

        Schema::table('mod_actions', function (Blueprint $table) {
            $table->unsignedBigInteger('moderator_id')->nullable()->change();
            $table->foreign('moderator_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mod_actions', function (Blueprint $table) {
            $table->dropForeign(['moderator_id']);
        });

        Schema::table('mod_actions', function (Blueprint $table) {
            $table->unsignedBigInteger('moderator_id')->nullable(false)->change();
            $table->foreign('moderator_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
