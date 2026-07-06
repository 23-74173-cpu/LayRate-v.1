<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('weight_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hen_id')->constrained()->cascadeOnDelete();
            $table->date('check_date');
            $table->decimal('weight_kg', 5, 2);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weight_checks');
    }
};
