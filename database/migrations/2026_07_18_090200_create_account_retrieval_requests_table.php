<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_retrieval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('old_email');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->date('dob');

            $table->string('id_photo_path');
            $table->string('selfie_path');
            $table->text('ocr_extracted_data')->nullable();
            $table->decimal('face_match_score', 5, 4)->nullable();
            $table->boolean('face_match_passed')->nullable();
            $table->text('verification_notes')->nullable();

            $table->string('status')->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'old_email']);
            $table->index('requester_user_id');
            $table->index('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_retrieval_requests');
    }
};
