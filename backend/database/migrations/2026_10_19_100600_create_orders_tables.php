<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->decimal('total', 10, 3)->default(0);
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dish_id')->constrained()->restrictOnDelete();
            // Snapshot of the dish at order time, so later menu edits never rewrite history.
            $table->string('dish_name');
            $table->unsignedSmallInteger('quantity');
            $table->decimal('unit_price', 8, 3);
            $table->decimal('subtotal', 10, 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
