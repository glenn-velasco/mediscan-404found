<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_information_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_information_id')->constrained('medical_information')->cascadeOnDelete();

            $table->string('name');
            $table->string('relationship')->nullable();
            $table->string('phone_number');
            $table->string('phone_country_code');
            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            $table->index(['medical_information_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_information_contacts');
    }
};
