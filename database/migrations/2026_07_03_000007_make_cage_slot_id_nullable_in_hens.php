<?php

use Illuminate\Database\Migrations\Migration;

/** @see 2026_07_03_000002_make_cage_slot_id_nullable_on_hens_table.php */
return new class extends Migration {
    public function up(): void
    {
        // cage_slot_id was already made nullable by 2026_07_03_000002
        // This migration is intentionally empty — kept only to avoid
        // "migrations table has row but file is missing" errors.
    }

    public function down(): void
    {
        // Nothing to undo.
    }
};
