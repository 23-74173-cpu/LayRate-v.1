<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cage_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hen_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_cage_slot_id')->nullable()->constrained('cage_slots')->nullOnDelete();
            $table->foreignId('to_cage_slot_id')->constrained('cage_slots')->cascadeOnDelete();
            $table->date('transfer_date');
            $table->string('reason', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cage_transfers');
    }
};
