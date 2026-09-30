<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Enforce strict rule: Employees marked as Dead File must have their biometric device_user_id
     * released immediately so it can be assigned to new hires without conflicts.
     */
    public function up(): void
    {
        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'device_user_id')) {
            $deadEmployees = DB::table('employees')
                ->where(function ($q) {
                    $q->where('is_dead_file', true)
                      ->orWhere('status', 'dead_file');
                })
                ->whereNotNull('device_user_id')
                ->get();

            $now = now()->toDateTimeString();

            foreach ($deadEmployees as $emp) {
                $releasedId = $emp->device_user_id;
                $currentNotes = $emp->dead_file_notes ?? '';
                $appendNote = "[Biometric ID #{$releasedId} released for new employee reuse on {$now}]";
                $updatedNotes = trim($currentNotes . "\n" . $appendNote);

                DB::table('employees')
                    ->where('id', $emp->id)
                    ->update([
                        'device_user_id' => null,
                        'dead_file_notes' => $updatedNotes,
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Releasing biometric IDs is a permanent decoupling so new employees can take over the PINs.
    }
};
