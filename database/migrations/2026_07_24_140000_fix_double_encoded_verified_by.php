<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['allergies', 'conditions', 'diagnoses', 'medications'];

        foreach ($tables as $table) {
            $rows = DB::select(
                "SELECT id, verified_by FROM {$table} WHERE verified_by IS NOT NULL"
            );

            foreach ($rows as $row) {
                $raw = $row->verified_by;
                $fixed = $this->decodeVerifiedBy($raw);

                if ($fixed !== null) {
                    DB::table($table)
                        ->where('id', $row->id)
                        ->update(['verified_by' => $fixed]);
                }
            }
        }
    }

    /**
     * Recursively decode verified_by until we have a proper array.
     *
     * Handles: raw array, single-encoded JSON string, double-encoded JSON string.
     *
     * @return array<int, array{user_id: int, name: string, verified_at: string}>|null
     */
    private function decodeVerifiedBy(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        // Double-encoded: the decoded value is still a JSON string
        if (is_string($decoded)) {
            $decoded2 = json_decode($decoded, true);
            if (is_array($decoded2)) {
                return $decoded2;
            }
        }

        return null;
    }

    public function down(): void
    {
        // Data-only migration; nothing to reverse.
    }
};
