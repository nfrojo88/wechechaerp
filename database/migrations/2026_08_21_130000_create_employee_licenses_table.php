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
        if (!Schema::hasTable('employee_licenses')) {
            Schema::create('employee_licenses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->string('license_name'); // e.g., Practicing Attorney, Professional Engineer, Heavy Vehicle Driving License
                $table->string('issuing_organization')->nullable(); // e.g., Ministry of Justice, Ethiopian Construction Authority
                $table->string('license_number')->nullable();
                $table->date('issue_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->string('license_document')->nullable();
                $table->string('status')->default('active'); // active, expired, suspended
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_licenses');
    }
};
