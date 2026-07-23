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
        // Encrypted values are ciphertext, not queryable/indexable text of a
        // predictable length - varchar(255)/date columns can't safely hold
        // them, so every column that will carry an `encrypted` cast (or the
        // manual dob accessor) moves to `text`.
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

    /**
     * One-time backfill: encrypt plaintext values already in the table so
     * existing rows aren't left readable once the model casts flip on.
     *
     * Uses `Crypt::encryptString()`, not the `encrypt()` helper - Eloquent's
     * `encrypted` cast (and the manual dob accessor) decrypt with
     * `Crypt::decryptString()` (no unserialize), so encrypting with the
     * serializing `encrypt()` helper here would leave every value unreadable
     * through the model.
     */
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

        // Postgres won't auto-cast text -> date even when every value is a
        // valid date string - needs an explicit USING conversion, which
        // Schema::table()->change() has no way to express.
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
