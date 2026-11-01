<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The password change is deliberately the LAST statement in up() and the
     * FIRST statement in down(). If rollback fails because NULL passwords
     * exist, nothing else has been touched yet and the schema remains intact.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('student_id', 11)->nullable()->unique()->after('email');
            $table->string('campus')->default('pampanga')->after('student_id');
            $table->string('display_name')->nullable()->after('name');
            $table->timestamp('verified_at')->nullable()->after('email_verified_at');
        });

        // Auth is OTP-only (see docs/AUTH.md): no password is ever set or checked,
        // so users.password must accept NULL. Raw SQL avoids requiring doctrine/dbal.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN password DROP NOT NULL');
        } else {
            DB::statement('ALTER TABLE `users` MODIFY `password` VARCHAR(255) NULL');
        }
    }

    /**
     * Reverse the migrations.
     *
     * WARNING: restoring password to NOT NULL will fail if any user has a NULL
     * password. That is expected and acceptable. Because this runs first, a
     * failure here leaves the four new columns in place.
     */
    public function down(): void
    {
        // Idempotent: only alter if the column is currently nullable.
        $column = DB::selectOne(
            'SELECT is_nullable
             FROM information_schema.columns
             WHERE table_schema = current_schema()
               AND table_name = ?
               AND column_name = ?',
            ['users', 'password']
        );

        if ($column !== null && strtoupper($column->is_nullable) === 'YES') {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE users ALTER COLUMN password SET NOT NULL');
            } else {
                DB::statement('ALTER TABLE `users` MODIFY `password` VARCHAR(255) NOT NULL');
            }
        }

        if (Schema::hasColumn('users', 'student_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['student_id']);
            });
        }

        $columns = array_values(array_filter(
            ['student_id', 'campus', 'display_name', 'verified_at'],
            fn (string $name) => Schema::hasColumn('users', $name)
        ));

        if ($columns !== []) {
            Schema::table('users', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
