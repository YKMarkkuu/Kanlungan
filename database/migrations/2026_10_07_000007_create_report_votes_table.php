<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** choice: agree | false_report | wrong_tag. One vote per student per report. Never shown to peers. */
    public function up(): void
    {
        Schema::create('report_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->foreignId('voter_id')->constrained('users')->cascadeOnDelete();
            $table->string('choice', 20);
            $table->timestamps();

            $table->unique(['report_id', 'voter_id'], 'report_votes_one_per_voter');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_votes');
    }
};
