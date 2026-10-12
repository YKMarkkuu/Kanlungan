<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * mod_status: null | pending (waiting in the moderator queue) | reviewed
     * mod_subject: who the moderator is judging - 'content' (its author) or 'reporter' (false report)
     * referred_guidance_at: sent to the Guidance account. guidance_handled_at: Guidance marked it done.
     */
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('mod_status', 10)->nullable()->after('outcome');
            $table->string('mod_subject', 10)->nullable()->after('mod_status');
            $table->timestamp('referred_guidance_at')->nullable();
            $table->timestamp('guidance_handled_at')->nullable();

            $table->index('mod_status');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex(['mod_status']);
            $table->dropColumn(['mod_status', 'mod_subject', 'referred_guidance_at', 'guidance_handled_at']);
        });
    }
};
