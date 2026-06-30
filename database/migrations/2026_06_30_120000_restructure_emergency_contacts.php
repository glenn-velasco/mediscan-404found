<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_information', function (Blueprint $table) {
            $table->dropColumn([
                'emergency_contact_name',
                'emergency_contact_phone_country_code',
                'emergency_contact_phone',
                'emergency_contact_relationship',
            ]);
        });

        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_information_id')->constrained('medical_information')->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship')->nullable();
            $table->string('phone_country_code')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_contacts');

        Schema::table('medical_information', function (Blueprint $table) {
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone_country_code')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_relationship')->nullable();
        });
    }
};
