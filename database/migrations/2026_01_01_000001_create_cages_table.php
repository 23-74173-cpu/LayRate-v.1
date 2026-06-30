<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cages', function (Blueprint $table) {
            $table->id();
            $table->string('cage_code', 50)->unique();
            $table->string('location', 100)->default('');
            $table->unsignedTinyInteger('rows')->default(3);
            $table->unsignedTinyInteger('slots_per_row')->default(5);
            $table->unsignedTinyInteger('max_chickens_per_slot')->default(4);
            $table->unsignedInteger('total_capacity')->default(60);
            $table->tinyInteger('is_active')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cages');
    }
};
