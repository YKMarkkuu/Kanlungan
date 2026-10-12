<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * role: student (default) | moderator | guidance | admin
     * muted_until: cannot post/comment/report/vote until then. banned_at: cannot use the community.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 12)->default('student');
            }
            if (! Schema::hasColumn('users', 'muted_until')) {
                $table->timestamp('muted_until')->nullable();
            }
            if (! Schema::hasColumn('users', 'banned_at')) {
                $table->timestamp('banned_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'warnings_count')) {
                $table->unsignedInteger('warnings_count')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['role', 'muted_until', 'banned_at', 'warnings_count'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
