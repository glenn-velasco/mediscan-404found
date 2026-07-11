<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('id');
            $table->string('first_name')->nullable()->after('username');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('last_name')->nullable()->after('middle_name');
            $table->string('suffix')->nullable()->after('last_name');
            $table->date('dob')->nullable()->after('suffix');
            $table->string('gender')->nullable()->after('dob');
            $table->text('address')->nullable()->after('gender');
            $table->string('phone_number')->nullable()->after('address');
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->dropColumn([
                'username',
                'first_name',
                'middle_name',
                'last_name',
                'suffix',
                'dob',
                'gender',
                'address',
                'phone_number',
            ]);
        });
    }
};
