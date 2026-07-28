<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('record_type')->nullable()->after('subject_id');
            $table->string('record_id')->nullable()->after('record_type');
            $table->string('ip_address')->nullable()->after('channel');

            $table->index(['record_type', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['record_type', 'record_id']);
            $table->dropColumn(['record_type', 'record_id', 'ip_address']);
        });
    }
};
