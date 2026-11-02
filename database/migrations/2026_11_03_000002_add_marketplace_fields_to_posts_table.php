<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marketplace listings only. price = whole pesos (0 = free). condition = one of Post::CONDITIONS.
     * Both stay NULL for every other category. `condition` is reserved in MySQL; schema builder quotes it.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->unsignedInteger('price')->nullable()->after('body');
            $table->string('condition', 20)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['price', 'condition']);
        });
    }
};
