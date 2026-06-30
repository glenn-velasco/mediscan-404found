<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_information_id')->constrained('medical_information')->cascadeOnDelete();
            $table->date('visit_date');
            $table->text('diagnosis')->nullable();
            $table->text('visit_notes')->nullable();
            $table->json('medications')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_records');
    }
};
