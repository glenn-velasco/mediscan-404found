<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('medical_attachments');
        Schema::dropIfExists('medical_records');
        Schema::dropIfExists('allergies');
        Schema::dropIfExists('emergency_contacts');
        Schema::dropIfExists('medical_information');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is irreversible — medical data storage is being
        // removed as part of a major architecture change (local-first mobile storage).
    }
};
