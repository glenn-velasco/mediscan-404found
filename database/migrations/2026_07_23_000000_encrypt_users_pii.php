<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('first_name')->nullable()->change();
            $table->text('middle_name')->nullable()->change();
            $table->text('last_name')->nullable()->change();
            $table->text('suffix')->nullable()->change();
            $table->text('dob')->nullable()->change();
            $table->text('address')->nullable()->change();
            $table->text('phone_number')->nullable()->change();
        });

        $this->encryptExistingRows();
    }

    private function encryptExistingRows(): void
    {
        DB::table('users')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('users')->where('id', $row->id)->update([
                    'first_name' => $row->first_name !== null ? Crypt::encryptString($row->first_name) : null,
                    'middle_name' => $row->middle_name !== null ? Crypt::encryptString($row->middle_name) : null,
                    'last_name' => $row->last_name !== null ? Crypt::encryptString($row->last_name) : null,
                    'suffix' => $row->suffix !== null ? Crypt::encryptString($row->suffix) : null,
                    'dob' => $row->dob !== null ? Crypt::encryptString($row->dob) : null,
                    'address' => $row->address !== null ? Crypt::encryptString($row->address) : null,
                    'phone_number' => $row->phone_number !== null ? Crypt::encryptString($row->phone_number) : null,
                ]);
            }
        });
    }

    public function down(): void
    {
        $this->decryptExistingRows();

        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->change();
            $table->string('middle_name')->nullable()->change();
            $table->string('last_name')->nullable()->change();
            $table->string('suffix')->nullable()->change();
            $table->text('address')->nullable()->change();
            $table->string('phone_number')->nullable()->change();
        });

        DB::statement('ALTER TABLE users ALTER COLUMN dob TYPE date USING dob::date');
    }

    private function decryptExistingRows(): void
    {
        DB::table('users')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('users')->where('id', $row->id)->update([
                    'first_name' => $row->first_name !== null ? Crypt::decryptString($row->first_name) : null,
                    'middle_name' => $row->middle_name !== null ? Crypt::decryptString($row->middle_name) : null,
                    'last_name' => $row->last_name !== null ? Crypt::decryptString($row->last_name) : null,
                    'suffix' => $row->suffix !== null ? Crypt::decryptString($row->suffix) : null,
                    'dob' => $row->dob !== null ? Crypt::decryptString($row->dob) : null,
                    'address' => $row->address !== null ? Crypt::decryptString($row->address) : null,
                    'phone_number' => $row->phone_number !== null ? Crypt::decryptString($row->phone_number) : null,
                ]);
            }
        });
    }
};
