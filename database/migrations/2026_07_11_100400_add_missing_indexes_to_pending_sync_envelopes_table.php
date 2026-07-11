<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_sync_envelopes', function (Blueprint $table) {
            $table->index('sender_id');
        });
    }

    public function down(): void
    {
        Schema::table('pending_sync_envelopes', function (Blueprint $table) {
            $table->dropIndex(['sender_id']);
        });
    }
};
