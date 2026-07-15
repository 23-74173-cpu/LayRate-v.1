<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('incubator_status');
    }

    public function down(): void
    {
        // No rollback needed — table was created by a previous migration
    }
};
