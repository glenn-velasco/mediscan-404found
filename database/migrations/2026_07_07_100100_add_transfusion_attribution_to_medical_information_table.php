<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_information', function (Blueprint $table) {
            $table->foreignId('transfusion_decision_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('transfusion_decision_at')->nullable();
            $table->foreignId('transfusion_consent_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('transfusion_consent_verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('medical_information', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transfusion_decision_by');
            $table->dropConstrainedForeignId('transfusion_consent_verified_by');
            $table->dropColumn(['transfusion_decision_at', 'transfusion_consent_verified_at']);
        });
    }
};
