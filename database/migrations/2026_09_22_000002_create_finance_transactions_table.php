<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_transactions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['income', 'expense']);
            // Single shared category list across income and expense, with
            // `type` as the separate flag distinguishing which side a given
            // category was recorded under (e.g. "Egg Sales" as income,
            // "Feed Cost" as expense) — final category list is still to be
            // confirmed with stakeholders, this is scaffolding.
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
