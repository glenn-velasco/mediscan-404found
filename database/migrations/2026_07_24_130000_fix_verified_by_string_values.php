<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['allergies', 'conditions', 'diagnoses', 'medications'];

        foreach ($tables as $table) {
            $rows = DB::select(
                "SELECT id, verified_by FROM {$table} WHERE verified_by IS NOT NULL AND verified_by::text NOT IN ('[]', 'null', '\"\"')"
            );

            foreach ($rows as $row) {
                $value = $row->verified_by;

                if (is_string($value)) {
                    $decoded = json_decode($value, true);

                    if (is_array($decoded)) {
                        DB::table($table)
                            ->where('id', $row->id)
                            ->update(['verified_by' => $decoded]);
                    } else {
                        DB::table($table)
                            ->where('id', $row->id)
                            ->update(['verified_by' => null]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // Data-only migration; nothing to reverse.
    }
};
