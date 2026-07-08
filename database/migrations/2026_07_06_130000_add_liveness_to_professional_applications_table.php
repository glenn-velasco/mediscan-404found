<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professional_applications', function (Blueprint $table) {
            // Raw multi-frame capture used only for the liveness check
            // (blink/motion detection); selfie_path holds the single frame
            // chosen as the canonical face-match image and profile photo.
            $table->text('selfie_frame_paths')->nullable()->after('selfie_path');
            $table->decimal('liveness_score', 5, 4)->nullable()->after('face_match_passed');
            $table->boolean('liveness_passed')->nullable()->after('liveness_score');
        });
    }

    public function down(): void
    {
        Schema::table('professional_applications', function (Blueprint $table) {
            $table->dropColumn(['selfie_frame_paths', 'liveness_score', 'liveness_passed']);
        });
    }
};
