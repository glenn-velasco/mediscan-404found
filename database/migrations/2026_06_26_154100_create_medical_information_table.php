<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_information', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix')->nullable();
            $table->string('full_name', 500)
                ->storedAs("trim(first_name || coalesce(' ' || nullif(middle_name, ''), '') || ' ' || last_name || coalesce(' ' || nullif(suffix, ''), ''))");
            $table->date('date_of_birth');
            $table->enum('gender', ['male', 'female']);
            $table->string('phone_country_code')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('blood_type')->nullable();
            $table->string('religion')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone_country_code')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_relationship')->nullable();
            $table->boolean('no_blood_transfusion')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_information');
    }
};
