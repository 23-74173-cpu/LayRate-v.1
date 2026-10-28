<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('production_logs', function (Blueprint $table) {
            $table->string('source_key', 100)->nullable()->unique();
        });

        Schema::table('feed_consumption_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('feed_batch_id')->nullable()->change();
            $table->string('source_key', 100)->nullable()->unique();
            $table->decimal('crude_protein_pct', 5, 2)->nullable();
        });

        Schema::table('environmental_logs', function (Blueprint $table) {
            $table->string('source_key', 100)->nullable()->unique();
            $table->string('provenance', 40)->nullable();
            $table->boolean('humidity_corrected')->nullable();
        });

        Schema::table('cages', function (Blueprint $table) {
            $table->string('breed_snapshot', 80)->nullable();
        });

        Schema::table('mortality_logs', function (Blueprint $table) {
            $table->string('source_key', 100)->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('feed_consumption_logs', 'source_key')
            && DB::table('feed_consumption_logs')->where('source_key', 'like', 'production_data_clean:%')->exists()) {
            throw new \RuntimeException('Refusing to reverse historical import schema while imported unbatched feed rows remain.');
        }

        Schema::table('mortality_logs', function (Blueprint $table) {
            $table->dropUnique(['source_key']);
            $table->dropColumn('source_key');
        });

        Schema::table('environmental_logs', function (Blueprint $table) {
            $table->dropUnique(['source_key']);
            $table->dropColumn(['source_key', 'provenance', 'humidity_corrected']);
        });

        Schema::table('cages', function (Blueprint $table) {
            $table->dropColumn('breed_snapshot');
        });

        Schema::table('feed_consumption_logs', function (Blueprint $table) {
            $table->dropUnique(['source_key']);
            $table->dropColumn(['source_key', 'crude_protein_pct']);
            $table->unsignedBigInteger('feed_batch_id')->nullable(false)->change();
        });

        Schema::table('production_logs', function (Blueprint $table) {
            $table->dropUnique(['source_key']);
            $table->dropUnique('production_logs_slot_date_source_unique');
            $table->unique(['cage_slot_id', 'log_date'], 'production_logs_cage_slot_id_log_date_unique');
            $table->dropColumn('source_key');
        });
    }
};
