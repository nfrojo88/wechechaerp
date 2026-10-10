<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LetterSequence extends Model
{
    protected $table = 'letter_sequences';

    protected $fillable = [
        'year',
        'prefix',
        'current_number',
    ];

    protected $casts = [
        'year'           => 'integer',
        'current_number' => 'integer',
    ];

    /**
     * Atomically generate next sequential reference number for a given year.
     * Ensures numbers are unique per year, never reused, and concurrency-safe.
     *
     * @param int|null $year Defaults to current calendar year
     * @param string $prefix Defaults to 'LTR'
     * @return string e.g. "LTR-2026-0001"
     */
    public static function generateNext(?int $year = null, string $prefix = 'LTR'): string
    {
        $year = $year ?: intval(date('Y'));

        return DB::transaction(function () use ($year, $prefix) {
            $seq = static::where('year', $year)->lockForUpdate()->first();

            if (!$seq) {
                // Initialize sequence, checking if any existing letters already exist for this year
                $maxExisting = 0;
                try {
                    $prefixPattern = "{$prefix}-{$year}-";
                    $rawMax = Letter::where('letter_number', 'like', "{$prefixPattern}%")
                        ->selectRaw("MAX(CAST(SUBSTRING(letter_number, ?) AS UNSIGNED)) as max_seq", [strlen($prefixPattern) + 1])
                        ->value('max_seq');
                    $maxExisting = intval($rawMax ?? 0);
                } catch (\Throwable $e) {
                    $maxExisting = 0;
                }

                $seq = static::create([
                    'year'           => $year,
                    'prefix'         => $prefix,
                    'current_number' => $maxExisting,
                ]);

                // Re-fetch with lock
                $seq = static::where('year', $year)->lockForUpdate()->first();
            }

            $seq->increment('current_number');
            $newNumber = $seq->current_number;

            return sprintf('%s-%d-%04d', $seq->prefix, $seq->year, $newNumber);
        });
    }

    /**
     * Peek next suggested reference number without incrementing.
     */
    public static function peekNext(?int $year = null, string $prefix = 'LTR'): string
    {
        $year = $year ?: intval(date('Y'));
        $seq = static::where('year', $year)->first();
        $next = ($seq ? $seq->current_number : 0) + 1;

        return sprintf('%s-%d-%04d', $prefix, $year, $next);
    }
}
