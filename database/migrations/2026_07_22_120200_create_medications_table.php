<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('medical_information_id')->constrained('medical_information')->cascadeOnDelete();

            $table->text('name');
            $table->text('dosage')->nullable();
            // Not encrypted: short structured text ("twice daily"), not
            // independently identifying the way a medication name is.
            $table->string('frequency')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('medical_information_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medications');
    }
};
