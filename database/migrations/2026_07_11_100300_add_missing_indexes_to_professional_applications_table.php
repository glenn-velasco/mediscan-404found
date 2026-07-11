<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professional_applications', function (Blueprint $table) {
            $table->index(['user_id', 'status']);
            $table->index('status');
            $table->index('deleted_at');
            $table->index('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('professional_applications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['status']);
            $table->dropIndex(['deleted_at']);
            $table->dropIndex(['reviewed_by']);
        });
    }
};
