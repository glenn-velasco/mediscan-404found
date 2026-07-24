<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diagnoses', function (Blueprint $table) {
            $table->foreignId('diagnosed_by')->nullable()->after('medical_information_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('diagnoses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('diagnosed_by');
        });
    }
};
