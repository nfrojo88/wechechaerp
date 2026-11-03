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
        Schema::create('slip_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->enum('slip_type', ['receive', 'send'])->comment('GRN = receive, SIN = send');
            $table->string('label')->comment('E.g., "Receiving (GRN)"');
            $table->string('prefix')->nullable()->comment('E.g., "REC", "OUT", or NULL for numeric-only');
            $table->integer('book_start_no')->comment('First slip number in this book');
            $table->integer('book_end_no')->comment('Last slip number in this book');
            $table->integer('current_slip_no')->comment('Next slip to assign');
            $table->integer('used_count')->default(0)->comment('How many slips assigned');
            $table->enum('status', ['active', 'inactive', 'full'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'slip_type']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slip_sequences');
    }
};
