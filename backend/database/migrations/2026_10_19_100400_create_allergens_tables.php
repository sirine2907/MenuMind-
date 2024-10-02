<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allergens', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon', 16)->nullable();
            $table->timestamps();
        });

        Schema::create('allergen_dish', function (Blueprint $table) {
            $table->foreignId('allergen_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dish_id')->constrained()->cascadeOnDelete();

            $table->primary(['allergen_id', 'dish_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allergen_dish');
        Schema::dropIfExists('allergens');
    }
};
