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

            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix')->nullable();

            $table->date('dob');
            $table->string('gender');
            $table->string('blood_type')->nullable();
            $table->string('religion')->nullable();

            $table->json('address')->nullable();

            $table->boolean('no_blood_transfusion')->default(false);
            $table->string('avatar_path')->nullable();

            $table->timestamps();

            $table->index(['last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_information');
    }
};
