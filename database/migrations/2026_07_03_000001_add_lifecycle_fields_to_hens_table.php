<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hens', function (Blueprint $table) {
            $table->string('chicken_id', 20)->nullable()->unique()->after('id');
            $table->enum('sex', ['hen', 'cockerel', 'unknown'])->default('hen')->after('breed');
            $table->string('source', 200)->nullable()->after('sex');
            $table->string('initial_health_status', 100)->nullable()->after('source');
            $table->text('notes')->nullable()->after('initial_health_status');
        });
    }

    public function down(): void
    {
        Schema::table('hens', function (Blueprint $table) {
            $table->dropColumn(['chicken_id', 'sex', 'source', 'initial_health_status', 'notes']);
        });
    }
};
