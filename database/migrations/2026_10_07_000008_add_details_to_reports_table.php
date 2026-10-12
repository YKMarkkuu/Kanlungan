<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** "Add details (optional)" box from the report sheet. */
    public function up(): void
    {
        if (! Schema::hasColumn('reports', 'details')) {
            Schema::table('reports', function (Blueprint $table) {
                $table->string('details', 255)->nullable()->after('category');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('reports', 'details')) {
            Schema::table('reports', function (Blueprint $table) {
                $table->dropColumn('details');
            });
        }
    }
};
