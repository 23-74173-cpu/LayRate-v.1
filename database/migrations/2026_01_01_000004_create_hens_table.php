<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cage_slot_id')->constrained('cage_slots')->cascadeOnDelete();
            $table->string('tag_code', 50)->nullable()->unique();
            $table->date('date_acquired')->nullable();
            $table->date('placement_date')->nullable();
            $table->unsignedInteger('age_at_placement_weeks')->nullable();
            $table->unsignedInteger('flock_age_weeks')->default(0);
            $table->enum('breed', [
                'ISA Brown',
                'Lohmann Brown-Classic',
                'Dekalb White',
                'Hy-Line Brown',
                'Novogen Brown',
            ])->default('ISA Brown');
            $table->tinyInteger('is_active')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hens');
    }
};
