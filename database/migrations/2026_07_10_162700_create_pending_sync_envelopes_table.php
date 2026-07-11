<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pending_sync_envelopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('envelope_type'); // metadata label: allergy_verification, transfusion_witness, etc.
            $table->text('ciphertext'); // encrypted payload (sodium sealed-box), base64-encoded
            $table->string('status')->default('pending'); // pending, acknowledged, expired, unclaimed
            $table->timestamp('expires_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_sync_envelopes');
    }
};
