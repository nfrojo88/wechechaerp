<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // -- Add material request fields to support_tickets --------------------
        Schema::table('support_tickets', function (Blueprint $table) {
            if (!Schema::hasColumn('support_tickets', 'has_material_request')) {
                $table->boolean('has_material_request')->default(false)->after('attachments_notes');
            }
            if (!Schema::hasColumn('support_tickets', 'mr_justification')) {
                $table->text('mr_justification')->nullable()->after('has_material_request');
            }
            if (!Schema::hasColumn('support_tickets', 'mr_urgency')) {
                $table->string('mr_urgency', 50)->nullable()->after('mr_justification');
            }
            if (!Schema::hasColumn('support_tickets', 'mr_project_location')) {
                $table->string('mr_project_location', 255)->nullable()->after('mr_urgency');
            }
            // GM decision
            if (!Schema::hasColumn('support_tickets', 'mr_gm_status')) {
                $table->string('mr_gm_status', 50)->nullable()->after('mr_project_location');
                // Values: pending_gm | approved_to_store | rejected_by_gm
            }
            if (!Schema::hasColumn('support_tickets', 'mr_gm_decided_at')) {
                $table->timestamp('mr_gm_decided_at')->nullable()->after('mr_gm_status');
            }
            if (!Schema::hasColumn('support_tickets', 'mr_gm_notes')) {
                $table->text('mr_gm_notes')->nullable()->after('mr_gm_decided_at');
            }
            if (!Schema::hasColumn('support_tickets', 'mr_gm_decided_by')) {
                $table->unsignedBigInteger('mr_gm_decided_by')->nullable()->after('mr_gm_notes');
            }
            // Store Manager
            if (!Schema::hasColumn('support_tickets', 'mr_store_status')) {
                $table->string('mr_store_status', 50)->nullable()->after('mr_gm_decided_by');
                // Values: pending_store | dispatched | partially_dispatched | unavailable
            }
            if (!Schema::hasColumn('support_tickets', 'mr_store_dispatched_at')) {
                $table->timestamp('mr_store_dispatched_at')->nullable()->after('mr_store_status');
            }
            if (!Schema::hasColumn('support_tickets', 'mr_store_notes')) {
                $table->text('mr_store_notes')->nullable()->after('mr_store_dispatched_at');
            }
            if (!Schema::hasColumn('support_tickets', 'mr_store_managed_by')) {
                $table->unsignedBigInteger('mr_store_managed_by')->nullable()->after('mr_store_notes');
            }
            // Procurement lifecycle link
            if (!Schema::hasColumn('support_tickets', 'mr_purchase_request_id')) {
                $table->unsignedBigInteger('mr_purchase_request_id')->nullable()->after('mr_store_managed_by');
            }
            // Head Office Secretary Receipt
            if (!Schema::hasColumn('support_tickets', 'mr_secretary_received')) {
                $table->boolean('mr_secretary_received')->default(false)->after('mr_purchase_request_id');
            }
            if (!Schema::hasColumn('support_tickets', 'mr_secretary_received_at')) {
                $table->timestamp('mr_secretary_received_at')->nullable()->after('mr_secretary_received');
            }
            if (!Schema::hasColumn('support_tickets', 'mr_secretary_received_by')) {
                $table->unsignedBigInteger('mr_secretary_received_by')->nullable()->after('mr_secretary_received_at');
            }
            if (!Schema::hasColumn('support_tickets', 'mr_secretary_notes')) {
                $table->text('mr_secretary_notes')->nullable()->after('mr_secretary_received_by');
            }
            // SMS flags for MR
            if (!Schema::hasColumn('support_tickets', 'mr_sms_gm_sent')) {
                $table->boolean('mr_sms_gm_sent')->default(false)->after('mr_secretary_notes');
            }
            if (!Schema::hasColumn('support_tickets', 'mr_sms_store_sent')) {
                $table->boolean('mr_sms_store_sent')->default(false)->after('mr_sms_gm_sent');
            }
        });

        // -- Create material request line items table -------------------------
        if (!Schema::hasTable('it_material_request_items')) {
            Schema::create('it_material_request_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('support_ticket_id');
                $table->string('item_name', 255);
                $table->string('unit', 50)->nullable();
                $table->decimal('quantity', 10, 2)->default(1);
                $table->string('purpose', 500)->nullable();
                $table->string('urgency_level', 50)->nullable(); // low, medium, high, critical
                $table->string('store_dispatch_qty', 50)->nullable(); // filled by store manager
                $table->string('store_dispatch_status', 50)->nullable(); // available, partial, unavailable
                $table->text('store_notes')->nullable();
                $table->timestamps();

                $table->foreign('support_ticket_id')
                    ->references('id')->on('support_tickets')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        $cols = [
            'has_material_request', 'mr_justification', 'mr_urgency', 'mr_project_location',
            'mr_gm_status', 'mr_gm_decided_at', 'mr_gm_notes', 'mr_gm_decided_by',
            'mr_store_status', 'mr_store_dispatched_at', 'mr_store_notes', 'mr_store_managed_by',
            'mr_purchase_request_id', 'mr_secretary_received', 'mr_secretary_received_at',
            'mr_secretary_received_by', 'mr_secretary_notes', 'mr_sms_gm_sent', 'mr_sms_store_sent',
        ];
        Schema::table('support_tickets', function (Blueprint $table) use ($cols) {
            foreach ($cols as $col) {
                if (Schema::hasColumn('support_tickets', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::dropIfExists('it_material_request_items');
    }
};
