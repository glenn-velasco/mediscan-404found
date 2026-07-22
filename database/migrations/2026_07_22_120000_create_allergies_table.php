<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allergies', function (Blueprint $table) {
            // Client (mobile)-generated UUID, used as the real primary key -
            // not a separate int id + tracking column. The phone creates
            // this record offline before the server has ever seen it, so the
            // client owns the identity from the start; syncing later is a
            // plain PUT/DELETE against the same id, not an id hand-off.
            $table->uuid('id')->primary();
            $table->foreignId('medical_information_id')->constrained('medical_information')->cascadeOnDelete();

            $table->text('allergen');
            $table->text('reaction')->nullable();
            // Not encrypted: a bounded enum (mild/moderate/severe/life-threatening),
            // not free text - not identifying on its own.
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
