<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('culling_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hen_id')->constrained()->cascadeOnDelete();
            $table->date('cull_date');
            $table->enum('reason', ['low_production', 'illness', 'aggression', 'age', 'other']);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('culling_logs');
    }
};
