<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Source linking for auto-posted finance rows (idempotent original +
     * reversal pairs) and the autopost cutover key (empty = OFF).
     * Additive only; no backfill, no data changes.
     */
    public function up(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->string('source_type', 50)->nullable()->after('description');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->enum('kind', ['original', 'reversal'])->default('original')->after('source_id');
            $table->unique(['source_type', 'source_id', 'kind'], 'finance_source_kind_unique');
        });

        Setting::firstOrCreate(['key' => 'finance_autopost_start'], ['value' => null]);
    }

    public function down(): void
    {
        Setting::where('key', 'finance_autopost_start')->delete();

        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->dropUnique('finance_source_kind_unique');
            $table->dropColumn(['source_type', 'source_id', 'kind']);
        });
    }
};
