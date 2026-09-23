<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReceiptsTable extends Migration
{
    public function up()
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique(); // RCP-YYYYMMDD-XXXX

            // Ownership & linking
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();

            // Parsed vendor info
            $table->string('vendor_name')->nullable();
            $table->string('vendor_tin')->nullable();

            // Financial fields
            $table->date('receipt_date')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('currency', 10)->default('ETB');

            // Classification
            $table->string('category')->default('other');
            $table->text('description')->nullable();

            // File storage
            $table->string('file_path');
            $table->string('file_type', 20)->default('image'); // image | pdf

            // OCR output
            $table->longText('ocr_raw_text')->nullable();
            $table->json('parsed_data')->nullable();
            $table->string('parse_status', 20)->default('pending'); // pending | parsed | failed
            $table->text('parse_error')->nullable();

            // Approval workflow
            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('receipts');
    }
}
