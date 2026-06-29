<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        Schema::dropIfExists('production_logs');
        Schema::dropIfExists('hens');
        Schema::dropIfExists('cage_slots');
        Schema::dropIfExists('cages');

        Schema::create('cages', function (Blueprint $table) {
            $table->id();
            $table->string('cage_code', 50)->unique();
            $table->string('location', 100)->default('');
            $table->unsignedInteger('rows')->default(1);
            $table->unsignedInteger('slots_per_row')->default(1);
            $table->unsignedInteger('max_chickens_per_slot')->default(1);
            $table->tinyInteger('is_active')->default(1);
            $table->timestamps();
        });

        Schema::create('cage_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cage_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->unsignedInteger('column_number');
            $table->unsignedInteger('slot_number');
            $table->unsignedInteger('current_occupancy')->default(0);
            $table->boolean('has_sensor')->default(false);
            $table->string('sensor_device_id', 100)->nullable();
            $table->timestamps();
            $table->unique(['cage_id', 'row_number', 'column_number']);
        });

        Schema::create('hens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cage_slot_id')->constrained()->cascadeOnDelete();
            $table->string('tag_code', 50)->nullable()->unique();
            $table->date('date_acquired')->nullable();
            $table->unsignedInteger('flock_age_weeks')->default(0);
            $table->enum('breed', [
                'ISA Brown',
                'Lohmann Brown-Classic',
                'Dekalb White',
                'Hy-Line Brown',
                'Novogen Brown',
            ])->default('ISA Brown');
            $table->date('placement_date')->nullable();
            $table->unsignedInteger('age_at_placement_weeks')->nullable();
            $table->tinyInteger('is_active')->default(1);
            $table->timestamps();
        });

        Schema::create('production_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cage_slot_id')->constrained()->cascadeOnDelete();
            $table->date('log_date');
            $table->unsignedInteger('egg_count')->default(0);
            $table->unsignedInteger('hen_count')->default(0);
            $table->decimal('hdep', 5, 2)->default(0);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('overridden_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('overridden_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['cage_slot_id', 'log_date']);
        });

        DB::table('environmental_logs')->truncate();
        DB::table('feed_consumption_logs')->truncate();
        DB::table('alerts')->truncate();
        DB::table('mortality_logs')->truncate();
        DB::table('forecasts')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        Schema::dropIfExists('production_logs');
        Schema::dropIfExists('hens');
        Schema::dropIfExists('cage_slots');
        Schema::dropIfExists('cages');

        Schema::create('cages', function (Blueprint $table) {
            $table->id();
            $table->string('cage_code', 50)->unique();
            $table->string('location', 100)->default('');
            $table->unsignedInteger('capacity')->default(120);
            $table->tinyInteger('is_active')->default(1);
            $table->boolean('has_sensor')->default(false);
            $table->string('sensor_device_id', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('hens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cage_id')->constrained()->cascadeOnDelete();
            $table->string('tag_code', 50)->nullable()->unique();
            $table->date('date_acquired')->nullable();
            $table->unsignedInteger('flock_age_weeks')->default(0);
            $table->enum('breed', [
                'ISA Brown', 'Lohmann Brown-Classic', 'Dekalb White', 'Hy-Line Brown', 'Novogen Brown',
            ])->default('ISA Brown');
            $table->date('placement_date')->nullable();
            $table->unsignedInteger('age_at_placement_weeks')->nullable();
            $table->tinyInteger('is_active')->default(1);
            $table->timestamps();
        });

        Schema::create('production_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cage_id')->constrained()->cascadeOnDelete();
            $table->date('log_date');
            $table->unsignedInteger('egg_count')->default(0);
            $table->unsignedInteger('hen_count')->default(0);
            $table->decimal('hdep', 5, 2)->default(0);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('overridden_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('overridden_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['cage_id', 'log_date']);
        });

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
