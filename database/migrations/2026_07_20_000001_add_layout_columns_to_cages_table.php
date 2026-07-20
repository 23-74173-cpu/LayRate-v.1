<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ╔══════════════════════════════════════════════════════════════════╗
     * ║  CORRECTIVE MIGRATION — Schema drift fix                        ║
     * ║                                                                  ║
     * ║  The production `cages` table was created by an older version    ║
     * ║  of migration 2026_01_01_000001 that lacked `rows`,              ║
     * ║  `slots_per_row`, `max_chickens_per_slot`, and `total_capacity`. ║
     * ║  The original migration could not be re-run (already migrated).  ║
     * ║  This migration adds the missing columns and backfills values.   ║
     * ╚══════════════════════════════════════════════════════════════════╝
     */
    public function up(): void
    {
        /**
         * ╔══════════════════════════════════════════════════════════╗
         * ║  ⚠️  STOPGAP WARNING — READ BEFORE DEPLOYING             ║
         * ║                                                          ║
         * ║  The default values below (rows=3, slots_per_row=10,     ║
         * ║  max_chickens_per_slot=4) produce 30 slots × 4 hens     ║
         * ║  = 120 total_capacity, which matches the existing        ║
         * ║  `capacity` column value for all 4 real cages.          ║
         * ║                                                          ║
         * ║  However, the ACTUAL farm layout (number of physical    ║
         * ║  rows, slots per row, and chickens per slot) is         ║
         * ║  UNKNOWN and MUST be confirmed by the farm operator.    ║
         * ║  These placeholder values are ONLY a stopgap so the     ║
         * ║  UI renders without SyntaxErrors.  Edit each cage       ║
         * ║  via the Cage Management → Edit modal to set the        ║
         * ║  real dimensions once known.                            ║
         * ╚══════════════════════════════════════════════════════════╝
         */

        // Idempotent: skip if columns already exist (dev env with fresh schema).
        if (! Schema::hasColumn('cages', 'rows')) {
            Schema::table('cages', function (Blueprint $table) {
                $table->unsignedTinyInteger('rows')->default(3)->after('is_active');
                $table->unsignedTinyInteger('slots_per_row')->default(10)->after('rows');
                $table->unsignedTinyInteger('max_chickens_per_slot')->default(4)->after('slots_per_row');
                $table->unsignedInteger('total_capacity')->nullable()->after('max_chickens_per_slot');
            });
        }

        // Backfill total_capacity from the old `capacity` column if that column exists.
        // The old `capacity` column is deliberately preserved as a safety
        // net until the operator confirms the real layout dimensions.
        if (Schema::hasColumn('cages', 'capacity')) {
            DB::table('cages')->update([
                'total_capacity' => DB::raw('`capacity`'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('cages', function (Blueprint $table) {
            $table->dropColumn(['rows', 'slots_per_row', 'max_chickens_per_slot', 'total_capacity']);
        });
    }
};
