<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('cage_slot_id')->nullable()->change();
        });

        Schema::table('environmental_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('cage_id')->nullable()->change();
        });

        Schema::table('feed_consumption_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('cage_id')->nullable()->change();
        });

        Schema::table('mortality_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('cage_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('production_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('cage_slot_id')->nullable(false)->change();
        });

        Schema::table('environmental_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('cage_id')->nullable(false)->change();
        });

        Schema::table('feed_consumption_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('cage_id')->nullable(false)->change();
        });

        Schema::table('mortality_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('cage_id')->nullable(false)->change();
        });
    }
};
