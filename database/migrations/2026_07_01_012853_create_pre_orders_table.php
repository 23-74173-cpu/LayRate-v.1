<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pre_orders', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name', 255);
            $table->string('customer_reference', 100)->nullable();
            $table->string('egg_size', 10);
            $table->unsignedInteger('egg_count');
            $table->date('requested_date');
            $table->date('fulfillment_date')->nullable();
            $table->string('status', 15)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_orders');
    }
};
