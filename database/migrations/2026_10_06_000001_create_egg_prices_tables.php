<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-configured egg prices per size (per tray + per piece, either
     * nullable) plus an append-only history so price changes stay
     * attributable. Prices start empty — admins enter them in the UI.
     */
    public function up(): void
    {
        Schema::create('egg_prices', function (Blueprint $table) {
            $table->id();
            $table->string('egg_size', 10)->unique();
            $table->decimal('price_per_tray', 10, 2)->nullable();
            $table->decimal('price_per_piece', 10, 2)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('egg_price_histories', function (Blueprint $table) {
            $table->id();
            $table->string('egg_size', 10);
            $table->decimal('old_price_per_tray', 10, 2)->nullable();
            $table->decimal('old_price_per_piece', 10, 2)->nullable();
            $table->decimal('new_price_per_tray', 10, 2)->nullable();
            $table->decimal('new_price_per_piece', 10, 2)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egg_price_histories');
        Schema::dropIfExists('egg_prices');
    }
};
