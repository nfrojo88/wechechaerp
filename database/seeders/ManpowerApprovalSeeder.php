<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Worker;
use App\Models\DailyManpowerSheet;
use App\Models\DailyManpowerLine;
use App\Models\WeeklyManpowerBatch;
use App\Models\WeeklyBatchItem;
use App\Models\AttendanceRawPunch;
use App\Models\Project;
use App\Models\User;
use App\Services\ManpowerApprovalService;
use Carbon\Carbon;

class ManpowerApprovalSeeder extends Seeder
{
    public function run(): void
    {
        $project = Project::first() ?? Project::create([
            'name'        => 'Chafe Primary School G+4 Building',
            'code'        => 'chefe',
            'status'      => 'active',
            'location'    => 'Around Ayat 49',
            'description' => 'Construction of G+4 school building, guard house, sport field, kitchen, toilet and fence.',
        ]);

        $engineer = User::role('site_engineer')->first() ?? User::first();
        $planner  = User::role(['planning_manager', 'planning'])->first() ?? $engineer;
        $gm       = User::role(['gm', 'general_manager'])->first() ?? $engineer;
        $finance  = User::role(['finance', 'finance_head'])->first() ?? $engineer;
        $hr       = User::role(['hr', 'hr_officer'])->first() ?? $engineer;

        // 1. Seed Sample Workers
        $workersData = [
            ['name' => 'Tadesse Bekele',    'phone' => '0911223344', 'trade' => 'Mason',        'daily_rate' => 700.00],
            ['name' => 'Mulugeta Alemu',    'phone' => '0922334455', 'trade' => 'Carpenter',    'daily_rate' => 650.00],
            ['name' => 'Kassahun Desta',    'phone' => '0933445566', 'trade' => 'Steel Fixer',  'daily_rate' => 750.00],
            ['name' => 'Getachew Haile',    'phone' => '0944556677', 'trade' => 'Electrician',  'daily_rate' => 800.00],
            ['name' => 'Birhanu Tesfaye',   'phone' => '0955667788', 'trade' => 'General Labor','daily_rate' => 450.00],
            ['name' => 'Solomon Worku',     'phone' => '0966778899', 'trade' => 'General Labor','daily_rate' => 450.00],
            ['name' => 'Dawit Girma',       'phone' => '0977889900', 'trade' => 'Plumber',      'daily_rate' => 720.00],
            ['name' => 'Yared Mengistu',    'phone' => '0988990011', 'trade' => 'Painter',      'daily_rate' => 600.00],
        ];

        $workers = [];
        foreach ($workersData as $i => $wd) {
            $code = 'WRK-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT);
            $workers[] = Worker::updateOrCreate(
                ['phone' => $wd['phone']],
                [
                    'worker_code' => $code,
                    'name'        => $wd['name'],
                    'trade'       => $wd['trade'],
                    'daily_rate'  => $wd['daily_rate'],
                    'status'      => 'active',
                    'created_by'  => $engineer?->id,
                ]
            );
        }

        // 2. Seed Raw Machine Punches
        foreach ($workers as $w) {
            AttendanceRawPunch::updateOrCreate(
                [
                    'device_user_id' => $w->phone,
                    'punch_time'     => Carbon::yesterday()->setTime(7, 55, 0),
                ],
                [
                    'device_id'  => 'ZK-SITE-01',
                    'site_id'    => $project->id,
                    'punch_type' => 'check_in',
                ]
            );
            AttendanceRawPunch::updateOrCreate(
                [
                    'device_user_id' => $w->phone,
                    'punch_time'     => Carbon::yesterday()->setTime(17, 10, 0),
                ],
                [
                    'device_id'  => 'ZK-SITE-01',
                    'site_id'    => $project->id,
                    'punch_type' => 'check_out',
                ]
            );
        }

        // 3. Seed Returned Sheet for Site Engineer (Rejected by Planning Manager)
        $retSheet = DailyManpowerSheet::updateOrCreate(
            ['sheet_number' => 'MS-RET-DEMO-01'],
            [
                'project_id'          => $project->id,
                'date'                => Carbon::parse('2026-09-28'),
                'site_engineer_id'    => $engineer?->id ?? 1,
                'trade'               => 'Masonry',
                'gang_subcontractor'  => 'Gang Alpha',
                'status'              => ManpowerApprovalService::STATUS_REJECTED,
                'current_stage'       => ManpowerApprovalService::STAGE_SITE_ENGINEER,
                'total_headcount'     => 3,
                'total_regular_hours' => 24.0,
                'total_overtime_hours'=> 4.0,
                'total_amount'        => 2450.00,
                'total_adjusted_amount'=> 2100.00,
                'rejected_by_stage'   => ManpowerApprovalService::STAGE_PLANNING_MANAGER,
                'rejection_reason'    => 'Wrong rate',
                'rejection_comment'   => 'Carpenter rate was entered at ETB 800 instead of agreed contract rate ETB 650. Please adjust.',
                'rejected_by_user_id' => $planner?->id,
                'notes'               => 'Masonry block laying 3rd floor wall.',
            ]
        );

        $retSheet->lines()->delete();
        foreach (array_slice($workers, 0, 3) as $w) {
            DailyManpowerLine::create([
                'sheet_id'          => $retSheet->id,
                'worker_id'         => $w->id,
                'check_in'          => '08:00',
                'check_out'         => '17:00',
                'regular_hours'     => 8.0,
                'overtime_hours'    => 1.0,
                'daily_rate'        => $w->daily_rate,
                'amount'            => $w->daily_rate + ($w->daily_rate / 8 * 1.25),
                'adjusted_amount'   => $w->daily_rate,
                'attendance_status' => 'present',
                'source'            => 'machine',
                'device_user_id'    => $w->phone,
            ]);
        }

        // 4. Seed Sheet Pending Planning Manager Review
        $planSheet = DailyManpowerSheet::updateOrCreate(
            ['sheet_number' => 'MS-PLAN-DEMO-02'],
            [
                'project_id'          => $project->id,
                'date'                => Carbon::parse('2026-09-29'),
                'site_engineer_id'    => $engineer?->id ?? 1,
                'trade'               => 'Concrete Casting',
                'gang_subcontractor'  => 'Direct Site Labor',
                'status'              => ManpowerApprovalService::STATUS_SUBMITTED,
                'current_stage'       => ManpowerApprovalService::STAGE_PLANNING_MANAGER,
                'total_headcount'     => 4,
                'total_regular_hours' => 32.0,
                'total_overtime_hours'=> 8.0,
                'total_amount'        => 3450.00,
                'notes'               => 'Slab concrete pouring day 1.',
            ]
        );

        $planSheet->lines()->delete();
        foreach (array_slice($workers, 0, 4) as $w) {
            DailyManpowerLine::create([
                'sheet_id'          => $planSheet->id,
                'worker_id'         => $w->id,
                'check_in'          => '07:45',
                'check_out'         => '18:00',
                'regular_hours'     => 8.0,
                'overtime_hours'    => 2.0,
                'daily_rate'        => $w->daily_rate,
                'amount'            => $w->daily_rate + ($w->daily_rate / 8 * 2 * 1.25),
                'attendance_status' => 'present',
                'source'            => 'machine',
                'device_user_id'    => $w->phone,
            ]);
        }

        // 5. Seed HR Approved Sheet (Ready for Weekly Batch)
        $hrSheet = DailyManpowerSheet::updateOrCreate(
            ['sheet_number' => 'MS-HR-DEMO-03'],
            [
                'project_id'          => $project->id,
                'date'                => Carbon::parse('2026-09-30'),
                'site_engineer_id'    => $engineer?->id ?? 1,
                'trade'               => 'Steel Fixing',
                'gang_subcontractor'  => 'Steel Masters Gang',
                'status'              => ManpowerApprovalService::STATUS_HR_APPROVED,
                'current_stage'       => ManpowerApprovalService::STAGE_HR_OFFICER,
                'total_headcount'     => 4,
                'total_regular_hours' => 32.0,
                'total_overtime_hours'=> 0,
                'total_amount'        => 2900.00,
                'notes'               => 'Column rebar reinforcement fabrication.',
            ]
        );

        $hrSheet->lines()->delete();
        foreach (array_slice($workers, 2, 4) as $w) {
            DailyManpowerLine::create([
                'sheet_id'          => $hrSheet->id,
                'worker_id'         => $w->id,
                'check_in'          => '08:00',
                'check_out'         => '17:00',
                'regular_hours'     => 8.0,
                'overtime_hours'    => 0,
                'daily_rate'        => $w->daily_rate,
                'amount'            => $w->daily_rate,
                'attendance_status' => 'present',
                'source'            => 'machine',
                'device_user_id'    => $w->phone,
            ]);
        }

        // 6. Seed Weekly Batch Pending GM Approval
        $batchGm = WeeklyManpowerBatch::updateOrCreate(
            ['batch_number' => 'WMB-2026-W39-GM'],
            [
                'project_id'          => $project->id,
                'week_start'          => Carbon::parse('2026-09-21'),
                'week_end'            => Carbon::parse('2026-09-27'),
                'status'              => 'Submitted_GM',
                'current_stage'       => ManpowerApprovalService::STAGE_GM,
                'total_workers_count' => 5,
                'total_days_worked'   => 25.0,
                'total_gross_amount'  => 16500.00,
                'total_deductions'    => 400.00,
                'total_advances'      => 600.00,
                'total_net_payable'   => 15500.00,
                'prepared_by_hr_id'   => $hr?->id,
                'payment_notes'       => 'Consolidated week 39 labor wages for Chafe school project.',
            ]
        );

        $batchGm->items()->delete();
        foreach (array_slice($workers, 0, 5) as $w) {
            WeeklyBatchItem::create([
                'batch_id'             => $batchGm->id,
                'worker_id'            => $w->id,
                'days_worked'          => 5.0,
                'total_regular_hours'  => 40.0,
                'total_overtime_hours' => 2.0,
                'gross_amount'         => 3300.00,
                'deductions'           => 80.00,
                'advances'             => 120.00,
                'net_payable'          => 3100.00,
            ]);
        }

        // 7. Seed GM-Approved Batch Pending Finance Payment
        $batchFinance = WeeklyManpowerBatch::updateOrCreate(
            ['batch_number' => 'WMB-2026-W38-FIN'],
            [
                'project_id'          => $project->id,
                'week_start'          => Carbon::parse('2026-09-14'),
                'week_end'            => Carbon::parse('2026-09-20'),
                'status'              => 'GM_Approved',
                'current_stage'       => ManpowerApprovalService::STAGE_FINANCE,
                'total_workers_count' => 6,
                'total_days_worked'   => 30.0,
                'total_gross_amount'  => 21000.00,
                'total_deductions'    => 500.00,
                'total_advances'      => 1000.00,
                'total_net_payable'   => 19500.00,
                'prepared_by_hr_id'   => $hr?->id,
                'approved_by_gm_id'   => $gm?->id,
                'gm_approved_at'      => Carbon::now()->subDays(2),
                'gm_notes'            => 'Approved for disbursement per allocated project labor budget.',
            ]
        );

        $batchFinance->items()->delete();
        foreach (array_slice($workers, 0, 6) as $w) {
            WeeklyBatchItem::create([
                'batch_id'             => $batchFinance->id,
                'worker_id'            => $w->id,
                'days_worked'          => 5.0,
                'total_regular_hours'  => 40.0,
                'total_overtime_hours' => 0,
                'gross_amount'         => 3500.00,
                'deductions'           => 83.33,
                'advances'             => 166.67,
                'net_payable'          => 3250.00,
            ]);
        }
    }
}
