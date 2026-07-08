<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_information', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transfusion_consent_verified_by');
            $table->dropColumn('transfusion_consent_verified_at');

            // Multiple professionals can witness the same decision; each entry
            // is {user_id, name, witnessed_at}. The name is a snapshot taken at
            // witnessing time so the attestation survives account changes.
            $table->json('transfusion_witnesses')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('medical_information', function (Blueprint $table) {
            $table->dropColumn('transfusion_witnesses');

            $table->foreignId('transfusion_consent_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('transfusion_consent_verified_at')->nullable();
        });
    }
};
