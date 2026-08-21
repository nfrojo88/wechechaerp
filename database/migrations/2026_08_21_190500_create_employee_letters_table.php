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
        if (!Schema::hasTable('employee_letters')) {
            Schema::create('employee_letters', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->string('reference_number')->nullable()->unique();
                $table->string('letter_type'); // thanks_letter, appreciation, first_warning, second_warning, final_warning, show_cause, suspension, termination, promotion, other
                $table->string('title'); // Subject of the letter
                $table->text('content'); // Body of the letter / reasons / appreciation
                $table->string('severity')->default('info'); // positive, warning, danger, info
                $table->date('issued_date');
                $table->foreignId('issued_by')->nullable()->constrained('users')->onDelete('set null');
                $table->string('attachment_path')->nullable(); // Uploaded signed document
                $table->date('effective_date')->nullable();
                $table->text('action_required')->nullable(); // Corrective action or follow-up
                $table->string('acknowledgement_status')->default('acknowledged'); // pending, acknowledged, refused_to_sign
                $table->timestamp('acknowledged_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_letters');
    }
};
