<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Same file name and schema as the reverted 2026-09-22 finance table, so a
// database that already ran that version keeps its table as-is and a fresh
// database gets the identical one.
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('finance_transactions')) {
            return;
        }

        Schema::create('finance_transactions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['income', 'expense']);
            // Validated against FinanceTransaction::CATEGORIES for the chosen
            // type. Kept as a plain string so the category list can change
            // (still to be confirmed with the farm) without a migration.
            $table->string('category');
            $table->decimal('amount', 10, 2);
            $table->text('description')->nullable();
            $table->date('date');
            $table->foreignId('cage_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('date');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_transactions');
    }
};
