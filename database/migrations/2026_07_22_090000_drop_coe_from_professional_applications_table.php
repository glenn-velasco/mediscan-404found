<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professional_applications', function (Blueprint $table) {
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
