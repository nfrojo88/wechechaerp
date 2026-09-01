<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('subcon_agreements')) {
            Schema::create('subcon_agreements', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('agreement_no', 50)->unique();
                $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
                $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
                $table->foreignId('takeoff_sheet_id')->nullable();
                $table->string('subcontractor_name', 255)->nullable();
                $table->string('subcontractor_contact', 255)->nullable();
                $table->text('scope_of_work')->nullable();
                $table->text('work_description')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->decimal('contract_value', 18, 2)->default(0);
                $table->decimal('total_amount', 18, 2)->default(0);
                $table->decimal('paid_to_date', 18, 2)->default(0);
                $table->decimal('retention_percent', 5, 2)->default(10);
                $table->decimal('retention_amount', 18, 2)->default(0);
                $table->string('status', 50)->default('draft')->index();
                $table->text('terms_conditions')->nullable();
                $table->string('agreement_file', 500)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            Schema::table('subcon_agreements', function (Blueprint $table) {
                if (!Schema::hasColumn('subcon_agreements', 'supplier_id')) {
                    $table->foreignId('supplier_id')->nullable()->after('project_id')->constrained('suppliers')->nullOnDelete();
                }
                if (!Schema::hasColumn('subcon_agreements', 'takeoff_sheet_id')) {
                    $table->foreignId('takeoff_sheet_id')->nullable()->after('supplier_id');
                }
                if (!Schema::hasColumn('subcon_agreements', 'work_description')) {
                    $table->text('work_description')->nullable()->after('scope_of_work');
                }
                if (!Schema::hasColumn('subcon_agreements', 'total_amount')) {
                    $table->decimal('total_amount', 18, 2)->default(0)->after('contract_value');
                }
                if (!Schema::hasColumn('subcon_agreements', 'agreement_file')) {
                    $table->string('agreement_file', 500)->nullable()->after('terms_conditions');
                }
                if (!Schema::hasColumn('subcon_agreements', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable()->after('approved_at');
                }
                if (!Schema::hasColumn('subcon_agreements', 'subcontractor_name')) {
                    $table->string('subcontractor_name', 255)->nullable()->after('takeoff_sheet_id');
                }
                if (!Schema::hasColumn('subcon_agreements', 'subcontractor_contact')) {
                    $table->string('subcontractor_contact', 255)->nullable()->after('subcontractor_name');
                }
                if (!Schema::hasColumn('subcon_agreements', 'scope_of_work')) {
                    $table->text('scope_of_work')->nullable()->after('subcontractor_contact');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safe down
    }
};
