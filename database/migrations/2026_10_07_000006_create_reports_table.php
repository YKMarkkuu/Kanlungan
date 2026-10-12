<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row = one report "case" on a post or comment.
     * status: open (glowing red, waiting for someone to start voting) -> voting -> resolved.
     * outcome (only when resolved): upheld | false_report | wrong_tag | no_consensus.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reportable_type');
            $table->unsignedBigInteger('reportable_id');
            $table->string('category', 20);
            $table->string('status', 10)->default('open');
            $table->string('outcome', 20)->nullable();
            $table->timestamp('voting_started_at')->nullable();
            $table->timestamp('voting_ends_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // A student can report the same thing only once.
            $table->unique(['reporter_id', 'reportable_type', 'reportable_id'], 'reports_one_per_reporter');
            $table->index(['reportable_type', 'reportable_id'], 'reports_target');
            $table->index(['status', 'voting_ends_at'], 'reports_due');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
