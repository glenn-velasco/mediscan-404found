<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allergies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('medical_information_id')->constrained('medical_information')->cascadeOnDelete();
            $table->text('allergen');
            $table->text('reaction')->nullable();
            $table->string('severity');
            $table->timestamps();
            $table->softDeletes();

            $table->index('medical_information_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allergies');
    }
};
