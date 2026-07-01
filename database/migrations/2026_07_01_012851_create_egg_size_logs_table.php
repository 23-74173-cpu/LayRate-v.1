<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('egg_size_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_log_id')->constrained('production_logs')->cascadeOnDelete();
            $table->string('egg_size', 10);
            $table->unsignedInteger('count');
            $table->timestamps();
            $table->unique(['production_log_id', 'egg_size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egg_size_logs');
    }
};
