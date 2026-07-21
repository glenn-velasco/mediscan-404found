<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professional_applications', function (Blueprint $table) {
            // Certificate of Employment requirement removed from the
            // professional application flow - dropped rather than left
            // nullable/unused, per an explicit decision to fully remove it.
            $table->dropColumn(['coe_path', 'coe_original_filename']);
        });
    }

    public function down(): void
    {
        Schema::table('professional_applications', function (Blueprint $table) {
            $table->string('coe_path')->nullable()->after('liveness_flash_frames');
            $table->string('coe_original_filename')->nullable()->after('coe_path');
        });
    }
};
