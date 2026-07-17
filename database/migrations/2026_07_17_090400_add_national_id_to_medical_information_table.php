<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_information', function (Blueprint $table) {
            $table->string('national_id')->nullable()->after('religion');
            $table->index('national_id');
        });
    }

    public function down(): void
    {
        Schema::table('medical_information', function (Blueprint $table) {
            $table->dropColumn('national_id');
        });
    }
};
