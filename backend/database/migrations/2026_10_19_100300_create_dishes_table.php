<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dishes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description');
            // TND is subdivided into 1000 millimes, hence 3 decimals.
            $table->decimal('price', 8, 3);
            $table->boolean('is_vegetarian')->default(false);
            $table->boolean('is_vegan')->default(false);
            $table->unsignedTinyInteger('spicy_level')->default(0);
            $table->boolean('available')->default(true);
            $table->string('image_url')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'slug']);
            $table->index(['restaurant_id', 'available', 'price']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dishes');
    }
};
