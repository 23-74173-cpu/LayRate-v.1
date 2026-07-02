<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hens', function (Blueprint $table) {
            $table->foreignId('cage_slot_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('hens', function (Blueprint $table) {
            $table->foreignId('cage_slot_id')->nullable(false)->change();
        });
    }
};
