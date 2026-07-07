<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('id_type');
            $table->string('issuing_country', 2);

            $table->string('profession')->nullable();
            $table->string('specialty')->nullable();
            // text, not string: Eloquent's `encrypted` cast stores ciphertext, which
            // can exceed varchar(255) even for a short plaintext license number.
            $table->text('license_number')->nullable();
            $table->date('license_expiry')->nullable();
            $table->string('full_name_on_id')->nullable();

            $table->string('id_photo_path');
            $table->string('selfie_path');
            $table->string('coe_path');
            $table->string('coe_original_filename')->nullable();

            // text, not json: `encrypted:array` stores ciphertext, which is not
            // valid JSON and Postgres would reject it in a native json column.
            $table->text('ocr_extracted_data')->nullable();
            $table->text('ocr_raw_response')->nullable();

            $table->decimal('face_match_score', 5, 4)->nullable();
            $table->boolean('face_match_passed')->nullable();

            $table->string('status')->default('processing');
            $table->text('rejection_reason')->nullable();
            $table->text('verification_notes')->nullable();
            $table->string('role_granted')->nullable();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX professional_applications_one_active_per_user
            ON professional_applications (user_id)
            WHERE status IN ('processing', 'pending_review') AND deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_applications');
    }
};
