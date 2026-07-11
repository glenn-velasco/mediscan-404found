<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_invitations', function (Blueprint $table) {
            $table->index('email');
            $table->index('invited_by');
            $table->index('role_id');
            $table->index(['accepted_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('user_invitations', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropIndex(['invited_by']);
            $table->dropIndex(['role_id']);
            $table->dropIndex(['accepted_at', 'expires_at']);
        });
    }
};
