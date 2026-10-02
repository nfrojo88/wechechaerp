<?php

namespace App\Services;

use App\Models\DailyManpowerSheet;
use App\Models\DailyManpowerLine;
use App\Models\ManpowerApprovalLog;
use App\Models\WeeklyManpowerBatch;
use App\Models\WeeklyBatchItem;
use App\Models\Worker;
use App\Models\AttendanceRawPunch;
use App\Models\User;
use App\Models\Project;
use App\Models\ActivityLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManpowerApprovalService
{
    /**
     * Role Stage Mapping
     */
    public const STAGE_SITE_ENGINEER    = 'site_engineer';
    public const STAGE_PLANNING_MANAGER = 'planning_manager';
    public const STAGE_COORDINATOR      = 'coordinator';
    public const STAGE_HR               = 'hr';
    public const STAGE_HR_OFFICER       = 'hr_officer';
    public const STAGE_GM               = 'gm';
    public const STAGE_FINANCE          = 'finance';
    public const STAGE_COMPLETED        = 'completed';

    /**
     * Sheet Statuses
     */
    public const STATUS_DRAFT                = 'Draft';
    public const STATUS_SUBMITTED            = 'Submitted';
    public const STATUS_PLANNING_APPROVED    = 'Planning Approved';
    public const STATUS_COORDINATOR_APPROVED = 'Coordinator Approved';
    public const STATUS_HR_APPROVED          = 'HR Approved';
    public const STATUS_IN_WEEKLY_BATCH      = 'In Weekly Batch';
    public const STATUS_GM_APPROVED          = 'GM Approved';
    public const STATUS_PAID                 = 'Paid';
    public const STATUS_REJECTED             = 'Rejected';

    /**
     * Check if a user can act on the sheet at its current stage.
     */
    public static function canUserActOnSheet(User $user, DailyManpowerSheet $sheet): bool
    {
        if ($user->hasAnyRole(['admin', 'global_admin'])) {
            return true;
        }

        return match ($sheet->current_stage) {
            self::STAGE_SITE_ENGINEER => ($sheet->site_engineer_id === $user->id || $user->hasRole('site_engineer')),
            self::STAGE_PLANNING_MANAGER => $user->hasAnyRole(['planning_manager', 'planning', 'technical_manager']),
            self::STAGE_COORDINATOR => $user->hasRole('coordinator'),
            self::STAGE_HR => $user->hasAnyRole(['hr', 'hr_manager']),
            self::STAGE_HR_OFFICER => $user->hasAnyRole(['hr_officer', 'hr_manager', 'hr']),
            self::STAGE_GM => $user->hasAnyRole(['gm', 'general_manager']),
            self::STAGE_FINANCE => $user->hasAnyRole(['finance', 'finance_head']),
            default => false,
        };
    }

    /**
     * Check if a user can act on the weekly batch at its current stage.
     */
    public static function canUserActOnBatch(User $user, WeeklyManpowerBatch $batch): bool
    {
        if ($user->hasAnyRole(['admin', 'global_admin'])) {
            return true;
        }

        return match ($batch->current_stage) {
            self::STAGE_HR_OFFICER => $user->hasAnyRole(['hr_officer', 'hr_manager', 'hr']),
            self::STAGE_GM => $user->hasAnyRole(['gm', 'general_manager']),
            self::STAGE_FINANCE => $user->hasAnyRole(['finance', 'finance_head']),
            default => false,
        };
    }

    /**
     * Site Engineer submits daily manpower sheet to Planning Manager.
     */
    public function submitSheet(DailyManpowerSheet $sheet, User $user, ?string $notes = null): DailyManpowerSheet
    {
        if (!in_array($sheet->status, [self::STATUS_DRAFT, self::STATUS_REJECTED])) {
            throw ValidationException::withMessages([
                'status' => "Cannot submit sheet in '{$sheet->status}' status.",
            ]);
        }

        if ($sheet->lines()->count() === 0) {
            throw ValidationException::withMessages([
                'lines' => 'Cannot submit an empty manpower sheet with zero workers.',
            ]);
        }

        return DB::transaction(function () use ($sheet, $user, $notes) {
            $prevAmount = $sheet->total_amount;
            $this->recalculateSheetTotals($sheet);

            $sheet->update([
                'status'            => self::STATUS_SUBMITTED,
                'current_stage'     => self::STAGE_PLANNING_MANAGER,
                'rejection_reason'  => null,
                'rejection_comment' => null,
                'rejected_by_stage' => null,
                'notes'             => $notes ?? $sheet->notes,
            ]);

            ManpowerApprovalLog::create([
                'record_type'   => 'daily_sheet',
                'record_id'     => $sheet->id,
                'stage'         => self::STAGE_SITE_ENGINEER,
                'action'        => $sheet->rejection_reason ? 'resubmitted' : 'submitted',
                'user_id'       => $user->id,
                'comment'       => $notes ?: 'Submitted for Planning Manager approval',
                'amount_before' => $prevAmount,
                'amount_after'  => $sheet->total_amount,
            ]);

            ActivityLog::log(
                'submitted',
                "Manpower Sheet [{$sheet->sheet_number}] submitted by {$user->name} to Planning Manager.",
                'Manpower Approval'
            );

            return $sheet;
        });
    }

    /**
     * Planning Manager Review (Approve or Reject with adjusted amounts).
     */
    public function reviewPlanning(
        DailyManpowerSheet $sheet,
        User $user,
        string $decision, // 'approve' or 'reject'
        ?string $reason = null,
        ?string $comment = null,
        array $lineAdjustments = []
    ): DailyManpowerSheet {
        if ($sheet->current_stage !== self::STAGE_PLANNING_MANAGER || $sheet->status !== self::STATUS_SUBMITTED) {
            throw ValidationException::withMessages(['status' => 'Sheet is not pending Planning Manager review.']);
        }

        return DB::transaction(function () use ($sheet, $user, $decision, $reason, $comment, $lineAdjustments) {
            $amountBefore = $sheet->effective_total_amount;
            
            // Apply line-by-line amount adjustments if provided
            if (!empty($lineAdjustments)) {
                foreach ($lineAdjustments as $lineId => $adjAmount) {
                    $line = $sheet->lines()->find($lineId);
                    if ($line && is_numeric($adjAmount)) {
                        $line->update(['adjusted_amount' => (float)$adjAmount]);
                    }
                }
                $this->recalculateSheetTotals($sheet);
            }

            if ($decision === 'approve') {
                $sheet->update([
                    'status'        => self::STATUS_PLANNING_APPROVED,
                    'current_stage' => self::STAGE_COORDINATOR,
                ]);

                $action = 'approved';
            } else {
                if (empty($reason) || empty($comment)) {
                    throw ValidationException::withMessages([
                        'comment' => 'Rejection reason and comment are required.',
                    ]);
                }

                $sheet->update([
                    'status'               => self::STATUS_REJECTED,
                    'current_stage'        => self::STAGE_SITE_ENGINEER, // Returned to Site Engineer
                    'rejected_by_stage'    => self::STAGE_PLANNING_MANAGER,
                    'rejection_reason'     => $reason,
                    'rejection_comment'    => $comment,
                    'rejected_by_user_id'  => $user->id,
                ]);

                $action = 'rejected';
            }

            ManpowerApprovalLog::create([
                'record_type'           => 'daily_sheet',
                'record_id'             => $sheet->id,
                'stage'                 => self::STAGE_PLANNING_MANAGER,
                'action'                => $action,
                'user_id'               => $user->id,
                'rejection_reason_code' => $reason,
                'comment'               => $comment ?: ($decision === 'approve' ? 'Approved by Planning Manager' : 'Rejected'),
                'amount_before'         => $amountBefore,
                'amount_after'          => $sheet->effective_total_amount,
                'line_adjustments'      => !empty($lineAdjustments) ? $lineAdjustments : null,
            ]);

            ActivityLog::log(
                $action,
                "Manpower Sheet [{$sheet->sheet_number}] {$action} by Planning Manager {$user->name}.",
                'Manpower Approval'
            );

            return $sheet;
        });
    }

    /**
     * Coordinator Review (Approve -> HR, Reject -> Site Engineer).
     */
    public function reviewCoordinator(
        DailyManpowerSheet $sheet,
        User $user,
        string $decision,
        ?string $reason = null,
        ?string $comment = null,
        array $lineAdjustments = []
    ): DailyManpowerSheet {
        if ($sheet->current_stage !== self::STAGE_COORDINATOR || $sheet->status !== self::STATUS_PLANNING_APPROVED) {
            throw ValidationException::withMessages(['status' => 'Sheet is not pending Coordinator review.']);
        }

        return DB::transaction(function () use ($sheet, $user, $decision, $reason, $comment, $lineAdjustments) {
            $amountBefore = $sheet->effective_total_amount;

            if (!empty($lineAdjustments)) {
                foreach ($lineAdjustments as $lineId => $adjAmount) {
                    $line = $sheet->lines()->find($lineId);
                    if ($line && is_numeric($adjAmount)) {
                        $line->update(['adjusted_amount' => (float)$adjAmount]);
                    }
                }
                $this->recalculateSheetTotals($sheet);
            }

            if ($decision === 'approve') {
                $sheet->update([
                    'status'        => self::STATUS_COORDINATOR_APPROVED,
                    'current_stage' => self::STAGE_HR,
                ]);
                $action = 'approved';
            } else {
                if (empty($reason) || empty($comment)) {
                    throw ValidationException::withMessages([
                        'comment' => 'Rejection reason and comment are required.',
                    ]);
                }

                $sheet->update([
                    'status'               => self::STATUS_REJECTED,
                    'current_stage'        => self::STAGE_SITE_ENGINEER,
                    'rejected_by_stage'    => self::STAGE_COORDINATOR,
                    'rejection_reason'     => $reason,
                    'rejection_comment'    => $comment,
                    'rejected_by_user_id'  => $user->id,
                ]);
                $action = 'rejected';
            }

            ManpowerApprovalLog::create([
                'record_type'           => 'daily_sheet',
                'record_id'             => $sheet->id,
                'stage'                 => self::STAGE_COORDINATOR,
                'action'                => $action,
                'user_id'               => $user->id,
                'rejection_reason_code' => $reason,
                'comment'               => $comment ?: ($decision === 'approve' ? 'Approved by Coordinator' : 'Rejected'),
                'amount_before'         => $amountBefore,
                'amount_after'          => $sheet->effective_total_amount,
                'line_adjustments'      => !empty($lineAdjustments) ? $lineAdjustments : null,
            ]);

            ActivityLog::log(
                $action,
                "Manpower Sheet [{$sheet->sheet_number}] {$action} by Coordinator {$user->name}.",
                'Manpower Approval'
            );

            return $sheet;
        });
    }

    /**
     * HR Review (Approve -> HR Officer weekly pool, Reject -> Site Engineer).
     */
    public function reviewHr(
        DailyManpowerSheet $sheet,
        User $user,
        string $decision,
        ?string $reason = null,
        ?string $comment = null,
        array $lineAdjustments = []
    ): DailyManpowerSheet {
        if ($sheet->current_stage !== self::STAGE_HR || $sheet->status !== self::STATUS_COORDINATOR_APPROVED) {
            throw ValidationException::withMessages(['status' => 'Sheet is not pending HR verification.']);
        }

        return DB::transaction(function () use ($sheet, $user, $decision, $reason, $comment, $lineAdjustments) {
            $amountBefore = $sheet->effective_total_amount;

            if (!empty($lineAdjustments)) {
                foreach ($lineAdjustments as $lineId => $adjAmount) {
                    $line = $sheet->lines()->find($lineId);
                    if ($line && is_numeric($adjAmount)) {
                        $line->update(['adjusted_amount' => (float)$adjAmount]);
                    }
                }
                $this->recalculateSheetTotals($sheet);
            }

            if ($decision === 'approve') {
                $sheet->update([
                    'status'        => self::STATUS_HR_APPROVED,
                    'current_stage' => self::STAGE_HR_OFFICER,
                ]);
                $action = 'approved';
            } else {
                if (empty($reason) || empty($comment)) {
                    throw ValidationException::withMessages([
                        'comment' => 'Rejection reason and comment are required.',
                    ]);
                }

                $sheet->update([
                    'status'               => self::STATUS_REJECTED,
                    'current_stage'        => self::STAGE_SITE_ENGINEER,
                    'rejected_by_stage'    => self::STAGE_HR,
                    'rejection_reason'     => $reason,
                    'rejection_comment'    => $comment,
                    'rejected_by_user_id'  => $user->id,
                ]);
                $action = 'rejected';
            }

            ManpowerApprovalLog::create([
                'record_type'           => 'daily_sheet',
                'record_id'             => $sheet->id,
                'stage'                 => self::STAGE_HR,
                'action'                => $action,
                'user_id'               => $user->id,
                'rejection_reason_code' => $reason,
                'comment'               => $comment ?: ($decision === 'approve' ? 'Approved by HR' : 'Rejected'),
                'amount_before'         => $amountBefore,
                'amount_after'          => $sheet->effective_total_amount,
                'line_adjustments'      => !empty($lineAdjustments) ? $lineAdjustments : null,
            ]);

            ActivityLog::log(
                $action,
                "Manpower Sheet [{$sheet->sheet_number}] {$action} by HR {$user->name}.",
                'Manpower Approval'
            );

            return $sheet;
        });
    }

    /**
     * HR Officer Weekly Batch Generator.
     * Takes an array of HR-approved sheet IDs and creates a WeeklyManpowerBatch.
     */
    public function generateWeeklyBatch(
        int $projectId,
        string $weekStart,
        string $weekEnd,
        array $sheetIds,
        User $hrUser,
        array $workerDeductions = [],
        array $workerAdvances = [],
        ?string $batchNotes = null
    ): WeeklyManpowerBatch {
        $sheets = DailyManpowerSheet::whereIn('id', $sheetIds)
            ->where('project_id', $projectId)
            ->where('status', self::STATUS_HR_APPROVED)
            ->whereNull('weekly_batch_id')
            ->with(['lines.worker'])
            ->get();

        if ($sheets->isEmpty()) {
            throw ValidationException::withMessages([
                'sheets' => 'No eligible HR-approved sheets found to include in the weekly batch.',
            ]);
        }

        return DB::transaction(function () use (
            $projectId,
            $weekStart,
            $weekEnd,
            $sheets,
            $hrUser,
            $workerDeductions,
            $workerAdvances,
            $batchNotes
        ) {
            $year = Carbon::parse($weekStart)->format('Y');
            $weekNum = Carbon::parse($weekStart)->format('W');
            $batchNumber = "WMB-{$year}-W{$weekNum}-" . strtoupper(substr(uniqid(), -4));

            // Aggregate by worker
            $workerMap = [];
            foreach ($sheets as $sheet) {
                foreach ($sheet->lines as $line) {
                    $wId = $line->worker_id;
                    if (!isset($workerMap[$wId])) {
                        $workerMap[$wId] = [
                            'worker'          => $line->worker,
                            'days_count'      => 0,
                            'regular_hours'   => 0,
                            'overtime_hours'  => 0,
                            'gross_amount'    => 0,
                            'sheet_ids'       => [],
                        ];
                    }

                    if ($line->attendance_status === 'present' || $line->effective_amount > 0) {
                        $workerMap[$wId]['days_count']++;
                    }
                    $workerMap[$wId]['regular_hours']  += (float)$line->regular_hours;
                    $workerMap[$wId]['overtime_hours'] += (float)$line->overtime_hours;
                    $workerMap[$wId]['gross_amount']   += (float)$line->effective_amount;
                    if (!in_array($sheet->id, $workerMap[$wId]['sheet_ids'])) {
                        $workerMap[$wId]['sheet_ids'][] = $sheet->id;
                    }
                }
            }

            $totalGross = 0;
            $totalDed   = 0;
            $totalAdv   = 0;
            $totalNet   = 0;
            $totalDays  = 0;

            $batch = WeeklyManpowerBatch::create([
                'batch_number'        => $batchNumber,
                'project_id'          => $projectId,
                'week_start'          => $weekStart,
                'week_end'            => $weekEnd,
                'status'              => 'Submitted_GM',
                'current_stage'       => self::STAGE_GM,
                'total_workers_count' => count($workerMap),
                'prepared_by_hr_id'   => $hrUser->id,
                'payment_notes'       => $batchNotes,
            ]);

            foreach ($workerMap as $wId => $data) {
                $ded = (float)($workerDeductions[$wId] ?? 0);
                $adv = (float)($workerAdvances[$wId] ?? 0);
                $gross = $data['gross_amount'];
                $net = max(0, $gross - $ded - $adv);

                $totalGross += $gross;
                $totalDed   += $ded;
                $totalAdv   += $adv;
                $totalNet   += $net;
                $totalDays  += $data['days_count'];

                WeeklyBatchItem::create([
                    'batch_id'             => $batch->id,
                    'worker_id'            => $wId,
                    'days_worked'          => $data['days_count'],
                    'total_regular_hours'  => $data['regular_hours'],
                    'total_overtime_hours' => $data['overtime_hours'],
                    'gross_amount'         => $gross,
                    'deductions'           => $ded,
                    'advances'             => $adv,
                    'net_payable'          => $net,
                    'daily_sheet_ids'      => $data['sheet_ids'],
                ]);
            }

            $batch->update([
                'total_days_worked'  => $totalDays,
                'total_gross_amount' => $totalGross,
                'total_deductions'   => $totalDed,
                'total_advances'     => $totalAdv,
                'total_net_payable'  => $totalNet,
            ]);

            // Lock included daily sheets
            DailyManpowerSheet::whereIn('id', $sheets->pluck('id'))->update([
                'status'          => self::STATUS_IN_WEEKLY_BATCH,
                'weekly_batch_id' => $batch->id,
                'current_stage'   => self::STAGE_GM,
            ]);

            ManpowerApprovalLog::create([
                'record_type'   => 'weekly_batch',
                'record_id'     => $batch->id,
                'stage'         => self::STAGE_HR_OFFICER,
                'action'        => 'batched',
                'user_id'       => $hrUser->id,
                'comment'       => "Generated Weekly Batch [{$batch->batch_number}] for {$batch->total_workers_count} workers and submitted to GM.",
                'amount_before' => 0,
                'amount_after'  => $totalNet,
            ]);

            ActivityLog::log(
                'created',
                "Weekly Manpower Batch [{$batch->batch_number}] created by {$hrUser->name} with net payable ETB " . number_format($totalNet, 2),
                'Manpower Approval'
            );

            return $batch;
        });
    }

    /**
     * General Manager Review of Weekly Batch.
     */
    public function reviewBatchGm(
        WeeklyManpowerBatch $batch,
        User $gmUser,
        string $decision,
        ?string $reason = null,
        ?string $comment = null
    ): WeeklyManpowerBatch {
        if ($batch->current_stage !== self::STAGE_GM || $batch->status !== 'Submitted_GM') {
            throw ValidationException::withMessages(['status' => 'Batch is not pending General Manager approval.']);
        }

        return DB::transaction(function () use ($batch, $gmUser, $decision, $reason, $comment) {
            if ($decision === 'approve') {
                $batch->update([
                    'status'            => 'GM_Approved',
                    'current_stage'     => self::STAGE_FINANCE,
                    'approved_by_gm_id' => $gmUser->id,
                    'gm_approved_at'    => now(),
                    'gm_notes'          => $comment,
                ]);

                // Update included sheets to GM Approved
                $batch->dailySheets()->update([
                    'status'        => self::STATUS_GM_APPROVED,
                    'current_stage' => self::STAGE_FINANCE,
                ]);

                $action = 'approved';
            } else {
                if (empty($reason) || empty($comment)) {
                    throw ValidationException::withMessages(['comment' => 'Rejection reason and comment are required.']);
                }

                $batch->update([
                    'status'            => 'Rejected',
                    'current_stage'     => self::STAGE_HR_OFFICER, // Returned to HR Officer
                    'rejection_reason'  => $reason,
                    'rejection_comment' => $comment,
                ]);

                // Unlock daily sheets back to HR Approved
                $batch->dailySheets()->update([
                    'status'          => self::STATUS_HR_APPROVED,
                    'weekly_batch_id' => null,
                    'current_stage'   => self::STAGE_HR_OFFICER,
                ]);

                $action = 'rejected';
            }

            ManpowerApprovalLog::create([
                'record_type'           => 'weekly_batch',
                'record_id'             => $batch->id,
                'stage'                 => self::STAGE_GM,
                'action'                => $action,
                'user_id'               => $gmUser->id,
                'rejection_reason_code' => $reason,
                'comment'               => $comment ?: ($decision === 'approve' ? 'Approved for payment by General Manager' : 'Rejected'),
                'amount_before'         => $batch->total_net_payable,
                'amount_after'          => $batch->total_net_payable,
            ]);

            ActivityLog::log(
                $action,
                "Weekly Manpower Batch [{$batch->batch_number}] {$action} by GM {$gmUser->name}.",
                'Manpower Approval'
            );

            return $batch;
        });
    }

    /**
     * Finance Payment Execution.
     */
    public function recordPayment(
        WeeklyManpowerBatch $batch,
        User $financeUser,
        string $paymentDate,
        string $paymentMethod,
        ?string $referenceNo = null,
        ?string $attachmentUrl = null,
        ?string $notes = null
    ): WeeklyManpowerBatch {
        if ($batch->current_stage !== self::STAGE_FINANCE || $batch->status !== 'GM_Approved') {
            throw ValidationException::withMessages(['status' => 'Batch is not authorized for payment.']);
        }

        return DB::transaction(function () use (
            $batch,
            $financeUser,
            $paymentDate,
            $paymentMethod,
            $referenceNo,
            $attachmentUrl,
            $notes
        ) {
            $batch->update([
                'status'             => 'Paid',
                'current_stage'      => self::STAGE_COMPLETED,
                'paid_by_finance_id' => $financeUser->id,
                'payment_date'       => $paymentDate,
                'payment_method'     => $paymentMethod,
                'payment_reference'  => $referenceNo,
                'payment_attachment' => $attachmentUrl,
                'payment_notes'      => $notes,
            ]);

            // Update included sheets to Paid (permanent read-only)
            $batch->dailySheets()->update([
                'status'        => self::STATUS_PAID,
                'current_stage' => self::STAGE_COMPLETED,
            ]);

            ManpowerApprovalLog::create([
                'record_type'   => 'weekly_batch',
                'record_id'     => $batch->id,
                'stage'         => self::STAGE_FINANCE,
                'action'        => 'paid',
                'user_id'       => $financeUser->id,
                'comment'       => "Paid via {$paymentMethod} (Ref: {$referenceNo}). Total: ETB " . number_format($batch->total_net_payable, 2),
                'amount_before' => $batch->total_net_payable,
                'amount_after'  => $batch->total_net_payable,
            ]);

            ActivityLog::log(
                'paid',
                "Weekly Manpower Batch [{$batch->batch_number}] marked as Paid by Finance {$financeUser->name}. Amount: ETB " . number_format($batch->total_net_payable, 2),
                'Manpower Approval'
            );

            return $batch;
        });
    }

    /**
     * Finance Hold action.
     */
    public function holdBatch(WeeklyManpowerBatch $batch, User $financeUser, string $holdReason): WeeklyManpowerBatch
    {
        if ($batch->current_stage !== self::STAGE_FINANCE || $batch->status !== 'GM_Approved') {
            throw ValidationException::withMessages(['status' => 'Batch cannot be put on hold.']);
        }

        return DB::transaction(function () use ($batch, $financeUser, $holdReason) {
            $batch->update([
                'status'      => 'Held',
                'hold_reason' => $holdReason,
            ]);

            ManpowerApprovalLog::create([
                'record_type'   => 'weekly_batch',
                'record_id'     => $batch->id,
                'stage'         => self::STAGE_FINANCE,
                'action'        => 'held',
                'user_id'       => $financeUser->id,
                'comment'       => "Batch held by Finance: {$holdReason}",
                'amount_before' => $batch->total_net_payable,
                'amount_after'  => $batch->total_net_payable,
            ]);

            return $batch;
        });
    }

    /**
     * Recalculate and update sheet aggregate totals.
     */
    public function recalculateSheetTotals(DailyManpowerSheet $sheet): void
    {
        $lines = $sheet->lines()->get();
        $headcount = 0;
        $totalRegH = 0;
        $totalOtH  = 0;
        $totalAmt  = 0;
        $totalAdj  = 0;
        $hasAdjustments = false;

        foreach ($lines as $line) {
            if ($line->attendance_status === 'present' || $line->amount > 0 || $line->adjusted_amount > 0) {
                $headcount++;
            }
            $totalRegH += (float)$line->regular_hours;
            $totalOtH  += (float)$line->overtime_hours;
            $totalAmt  += (float)$line->amount;

            if ($line->adjusted_amount !== null) {
                $hasAdjustments = true;
                $totalAdj += (float)$line->adjusted_amount;
            } else {
                $totalAdj += (float)$line->amount;
            }
        }

        $sheet->update([
            'total_headcount'       => $headcount,
            'total_regular_hours'   => $totalRegH,
            'total_overtime_hours'  => $totalOtH,
            'total_amount'          => $totalAmt,
            'total_adjusted_amount' => $hasAdjustments ? $totalAdj : null,
        ]);
    }
}
