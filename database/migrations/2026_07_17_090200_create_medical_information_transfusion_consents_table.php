<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_information_transfusion_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_information_id')->constrained('medical_information')->cascadeOnDelete();

            $table->string('consenter_name');
            $table->string('relationship_to_patient')->nullable();
            $table->timestamp('consented_at');

            $table->timestamps();

            $table->index('medical_information_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_information_transfusion_consents');
    }
};
