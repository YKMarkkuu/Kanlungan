<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('reports')
            ->whereIn('category', ['Self-harm', 'Threats'])
            ->whereIn('status', ['open', 'voting'])
            ->update([
                'status' => 'escalated',
                'mod_status' => 'pending',
                'mod_subject' => 'content',
                'voting_started_at' => null,
                'voting_ends_at' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Existing reports may already have received moderator action; do not revert that state.
    }
};
