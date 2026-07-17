<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_information_registration_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('candidate_medical_information_id')->constrained('medical_information')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'candidate_medical_information_id']);
            $table->index('requester_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_information_registration_matches');
    }
};
