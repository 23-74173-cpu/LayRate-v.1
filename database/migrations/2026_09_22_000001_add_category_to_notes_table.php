<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Same file name (and same column) as the reverted 2026-09-22 notes change, so
// a database that already ran that version skips this one, and a fresh
// database gets the identical column. The hasColumn() guard covers a database
// where the column exists but the migrations table lost track of it.
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('notes', 'category')) {
            return;
        }

        Schema::table('notes', function (Blueprint $table) {
            $table->string('category')->default('General')->after('body');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('notes', 'category')) {
            return;
        }

        Schema::table('notes', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
