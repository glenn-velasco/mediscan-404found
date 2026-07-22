<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('password');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix')->nullable();
            $table->date('dob');
            $table->string('gender');
            $table->string('address')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('phone_country_code')->nullable();
            $table->timestamps();

            $table->index('email');
        });

        Schema::create('medical_information_registration_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('pending_registration_id')->nullable()->constrained('pending_registrations')->cascadeOnDelete();
            $table->foreignId('candidate_medical_information_id')->constrained('medical_information')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'candidate_medical_information_id']);
            $table->index('requester_user_id');
            $table->index('pending_registration_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_information_registration_matches');
        Schema::dropIfExists('pending_registrations');
    }
};
