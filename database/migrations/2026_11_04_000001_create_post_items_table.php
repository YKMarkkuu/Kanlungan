<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extra Marketplace items. Single-item listings keep their price on posts.price and have no rows here.
     * posts.price always stores the lowest listed price.
     */
    public function up(): void
    {
        Schema::create('post_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->unsignedTinyInteger('position')->default(0);
            $table->string('label', 40)->nullable();
            $table->unsignedInteger('price');

            $table->unique(['post_id', 'position'], 'post_items_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_items');
    }
};
