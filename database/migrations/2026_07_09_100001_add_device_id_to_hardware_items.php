<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hardware_items', function (Blueprint $table) {
            $table->foreignId('device_id')->nullable()->after('cage_slot_id')->constrained('devices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hardware_items', function (Blueprint $table) {
            $table->dropForeign(['device_id']);
            $table->dropColumn('device_id');
        });
    }
};
