<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('production_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cage_id')->constrained()->cascadeOnDelete();
            $table->date('log_date');
            $table->unsignedInteger('egg_count')->default(0);
            $table->unsignedInteger('hen_count')->default(0);
            $table->decimal('hdep', 5, 2)->default(0);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['cage_id', 'log_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_logs');
    }
};
