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
        Schema::table('medical_information', function (Blueprint $table) {
            $table->dropIndex(['last_name', 'first_name']);
            $table->dropIndex(['national_id']);
        });

        Schema::table('medical_information', function (Blueprint $table) {
            $table->text('first_name')->change();
            $table->text('middle_name')->nullable()->change();
            $table->text('last_name')->change();
            $table->text('suffix')->nullable()->change();
            $table->text('dob')->change();
            $table->text('national_id')->nullable()->change();
            $table->text('address')->nullable()->change();
        });

        $this->encryptExistingRows();
    }

    private function encryptExistingRows(): void
    {
        DB::table('medical_information')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('medical_information')->where('id', $row->id)->update([
                    'first_name' => Crypt::encryptString($row->first_name),
                    'middle_name' => $row->middle_name !== null ? Crypt::encryptString($row->middle_name) : null,
                    'last_name' => Crypt::encryptString($row->last_name),
                    'suffix' => $row->suffix !== null ? Crypt::encryptString($row->suffix) : null,
                    'dob' => Crypt::encryptString($row->dob),
                    'national_id' => $row->national_id !== null ? Crypt::encryptString($row->national_id) : null,
                    'address' => $row->address !== null ? Crypt::encryptString($row->address) : null,
                ]);
            }
        });
    }

    public function down(): void
    {
        $this->decryptExistingRows();

        Schema::table('medical_information', function (Blueprint $table) {
            $table->string('first_name')->change();
            $table->string('middle_name')->nullable()->change();
            $table->string('last_name')->change();
            $table->string('suffix')->nullable()->change();
            $table->string('national_id')->nullable()->change();
            $table->json('address')->nullable()->change();
        });

        // Postgres won't auto-cast text -> date even when every value is a
        // valid date string - needs an explicit USING conversion, which
        // Schema::table()->change() has no way to express.
        DB::statement('ALTER TABLE medical_information ALTER COLUMN dob TYPE date USING dob::date');

        Schema::table('medical_information', function (Blueprint $table) {
            $table->index(['last_name', 'first_name']);
            $table->index('national_id');
        });
    }

    private function decryptExistingRows(): void
    {
        DB::table('medical_information')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('medical_information')->where('id', $row->id)->update([
                    'first_name' => Crypt::decryptString($row->first_name),
                    'middle_name' => $row->middle_name !== null ? Crypt::decryptString($row->middle_name) : null,
                    'last_name' => Crypt::decryptString($row->last_name),
                    'suffix' => $row->suffix !== null ? Crypt::decryptString($row->suffix) : null,
                    'dob' => Crypt::decryptString($row->dob),
                    'national_id' => $row->national_id !== null ? Crypt::decryptString($row->national_id) : null,
                    'address' => $row->address !== null ? Crypt::decryptString($row->address) : null,
                ]);
            }
        });
    }
};
