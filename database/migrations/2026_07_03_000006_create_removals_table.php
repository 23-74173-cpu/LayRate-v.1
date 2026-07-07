<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('removals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hen_id')->constrained()->cascadeOnDelete();
            $table->date('removal_date');
            $table->string('reason', 100);
            $table->string('destination', 200)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('removals');
    }
};
