<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // The PHI record a CRUD action touched, e.g. ('allergy', '<uuid>').
            // Separate from subject_id (a user) - one PHI record can be
            // touched by an action whose "subject" (the owning user) is
            // already captured, but knowing exactly which allergy/diagnosis/
            // medication/emergency_contact row changed is needed for a real
            // audit trail. Never store the PHI values themselves here - see
            // the `metadata` column's existing "non-PHI context" convention.
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
