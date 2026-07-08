<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professional_applications', function (Blueprint $table) {
            // Array of {path, color} pairs captured during the on-screen
            // RGB flash challenge - used by the liveness check to confirm
            // the face's lighting actually reacts to the displayed colors
            // (a printed photo or replayed video won't track that in sync).
            $table->text('liveness_flash_frames')->nullable()->after('selfie_frame_paths');
        });
    }

    public function down(): void
    {
        Schema::table('professional_applications', function (Blueprint $table) {
            $table->dropColumn('liveness_flash_frames');
        });
    }
};
