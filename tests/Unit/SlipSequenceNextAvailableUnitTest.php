<?php

namespace Tests\Unit;

use App\Models\SlipSequence;
use Tests\TestCase;

class SlipSequenceNextAvailableUnitTest extends TestCase
{
    public function test_sequence_defaults_to_book_start_when_current_not_specified()
    {
        $seq = new SlipSequence([
            'book_start_no'   => 100,
            'book_end_no'     => 200,
            'current_slip_no' => 100,
        ]);

        $emptyAssigned = collect([]);
        $next = $seq->nextFreeNumber(null, $emptyAssigned);

        $this->assertEquals(100, $next);
    }

    public function test_sequence_starts_from_custom_next_available_slip_number()
    {
        $seq = new SlipSequence([
            'book_start_no'   => 1000,
            'book_end_no'     => 1100,
            'current_slip_no' => 1025, // Store manager specifies to start at 1025
        ]);

        $emptyAssigned = collect([]);
        $next = $seq->nextFreeNumber(null, $emptyAssigned);

        // Must start with 1025, skipping 1000-1024
        $this->assertEquals(1025, $next);
    }

    public function test_sequence_increments_past_used_slips_from_custom_start()
    {
        $seq = new SlipSequence([
            'book_start_no'   => 1000,
            'book_end_no'     => 1100,
            'current_slip_no' => 1025,
        ]);

        // Slips 1025 and 1026 are already registered
        $assigned = collect([
            ['numeric_no' => 1025],
            ['numeric_no' => 1026],
        ]);

        $next = $seq->nextFreeNumber(null, $assigned);

        // Next available must be 1027
        $this->assertEquals(1027, $next);
    }

    public function test_remaining_slips_with_custom_starting_slip()
    {
        $seq = new SlipSequence([
            'book_start_no'   => 1000,
            'book_end_no'     => 1100,
            'current_slip_no' => 1050, // 51 slips remaining (1050 to 1100 inclusive)
        ]);

        $emptyAssigned = collect([]);
        $remaining = $seq->getRemainingSlips($emptyAssigned);

        $this->assertEquals(51, $remaining);
    }
}
