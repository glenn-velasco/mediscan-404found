<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professional_applications', function (Blueprint $table) {
            // Whether the client (mobile app via ML Kit) detected a blink
            // before submitting frames. When true, the server skips its own
            // blink detection and trusts the client-side result.
            $table->boolean('client_blink_detected')->default(false)->after('liveness_flash_frames');
        });
    }

    public function down(): void
    {
        Schema::table('professional_applications', function (Blueprint $table) {
            $table->dropColumn('client_blink_detected');
        });
    }
};
