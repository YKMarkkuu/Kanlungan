<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * snippet = short quote of the comment / post an alert is about (shown as a quote in the UI).
     * body stays for plain-text messages (moderator notices, report updates).
     */
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->string('snippet', 500)->nullable()->after('body');
        });

        DB::table('alerts')
            ->whereIn('kind', ['comment', 'helpful'])
            ->whereNotNull('body')
            ->orderBy('id')
            ->chunkById(200, function ($alerts) {
                foreach ($alerts as $alert) {
                    if (strlen($alert->body) >= 2 && str_starts_with($alert->body, '"') && str_ends_with($alert->body, '"')) {
                        DB::table('alerts')->where('id', $alert->id)->update([
                            'snippet' => substr($alert->body, 1, -1),
                            'body' => null,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        DB::table('alerts')
            ->whereIn('kind', ['comment', 'helpful'])
            ->whereNotNull('snippet')
            ->whereNull('body')
            ->update(['body' => DB::raw("CONCAT('\"', snippet, '\"')")]);

        Schema::table('alerts', function (Blueprint $table) {
            $table->dropColumn('snippet');
        });
    }
};
