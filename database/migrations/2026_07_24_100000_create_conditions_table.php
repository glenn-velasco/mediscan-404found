<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conditions', function (Blueprint $table) {
            // Client (mobile)-generated UUID, same identity design as allergies/
            // diagnoses/medications/emergency_contacts - see the `allergies`
            // migration's comment.
            $table->uuid('id')->primary();
            $table->foreignId('medical_information_id')->constrained('medical_information')->cascadeOnDelete();

            // A condition is the patient's own free-text note on their general health
            // state - deliberately not a fixed list/enum, unlike Diagnosis.severity -
            // see docs/DIAGNOSES.md for the condition vs. diagnosis distinction.
            $table->text('description');

            $table->timestamps();
            $table->softDeletes();

            $table->index('medical_information_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conditions');
    }
};
