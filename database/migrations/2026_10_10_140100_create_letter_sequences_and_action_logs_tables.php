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
        // 1. Dedicated Letter Sequences Table (Per year, atomic increment, never reused, locked)
        if (!Schema::hasTable('letter_sequences')) {
            Schema::create('letter_sequences', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('year')->unique();
                $table->string('prefix', 20)->default('LTR');
                $table->unsignedInteger('current_number')->default(0);
                $table->timestamps();
            });
        }

        // 2. Comprehensive Letter Action Audit Logs
        if (!Schema::hasTable('letter_action_logs')) {
            Schema::create('letter_action_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('letter_id')->constrained('letters')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('action', 50); // sent_to_secretary, numbered, categorized, person_selected, registered, etc.
                $table->text('description');
                $table->json('details')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['letter_id', 'created_at']);
                $table->index(['action', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letter_action_logs');
        Schema::dropIfExists('letter_sequences');
    }
};
