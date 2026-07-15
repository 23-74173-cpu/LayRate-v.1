<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('incubator_status', function (Blueprint $table) {
            $table->id();
            $table->float('temperature')->default(0.0);
            $table->float('humidity')->default(0.0);
            $table->integer('egg_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incubator_status');
    }
};
