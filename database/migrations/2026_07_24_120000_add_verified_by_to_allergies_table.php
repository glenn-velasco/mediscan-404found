<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('allergies', function (Blueprint $table) {
            $table->json('verified_by')->nullable()->after('severity');
        });
    }

    public function down(): void
    {
        Schema::table('allergies', function (Blueprint $table) {
            $table->dropColumn('verified_by');
        });
    }
};
