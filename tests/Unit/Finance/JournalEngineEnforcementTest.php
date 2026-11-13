<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;

class JournalEngineEnforcementTest extends TestCase
{
    /**
     * Forbidden patterns that bypass JournalEngine.
     */
    public static function getForbiddenPatterns(): array
    {
        return [
            'JournalEntry::create',
            'JournalEntryLine::create',
            'JournalEntry::insert',
            'JournalEntryLine::insert',
            'JournalEntry::forceCreate',
            'JournalEntryLine::forceCreate',
            'JournalEntry::firstOrCreate',
            'JournalEntryLine::firstOrCreate',
            'JournalEntry::updateOrCreate',
            'JournalEntryLine::updateOrCreate',
            'lines()->create',
            'lines()->createMany',
            'lines()->insert',
            'lines()->firstOrCreate',
            'lines()->updateOrCreate',
            'lines()->saveMany',
            'new JournalEntryLine',
            'new JournalEntry',
            'new \App\Models\JournalEntryLine',
            'new \App\Models\JournalEntry',
        ];
    }

    /**
     * Regex patterns targeting DB::insert, DB::table insert, ::insert, forceCreate, firstOrCreate, updateOrCreate, saveMany, query-builder variable inserts.
     */
    public static function getForbiddenRegexPatterns(): array
    {
        return [
            // DB raw insert
            '/DB::insert\s*\(/i',

            // DB::table insert variants on journal tables
            '/DB::table\(\s*[\'"]journal_entries[\'"]\s*\)->(?:insert|insertGetId|insertOrIgnore)/i',
            '/DB::table\(\s*[\'"]journal_entry_lines[\'"]\s*\)->(?:insert|insertGetId|insertOrIgnore)/i',

            // Eloquent methods on Journal models
            '/\bJournalEntryLine::(?:insert|create|createMany|forceCreate|firstOrCreate|updateOrCreate|saveMany)\b/i',
            '/\bJournalEntry::(?:insert|create|createMany|forceCreate|firstOrCreate|updateOrCreate|saveMany)\b/i',

            // Direct instantiation of Journal models
            '/new\s+(\\\\?App\\\\Models\\\\)?JournalEntryLine\b/i',
            '/new\s+(\\\\?App\\\\Models\\\\)?JournalEntry\b/i',

            // Relation createMany / saveMany / insert on lines relation
            '/->(?:lines|journalEntries)\(\)->(?:createMany|saveMany|insert|create|firstOrCreate|updateOrCreate)/i',

            // Direct ->save() or ->saveMany() invocations on journal entry line instances
            '/\$(?:line|journalEntryLine|entryLine|jel)->(?:save|saveMany)\s*\(/i',

            // Query builder variable inserts
            '/\$(?:table|query|builder|q)->(?:insert|insertGetId|insertOrIgnore)\s*\(/i',
        ];
    }

    /**
     * Check if a given line of code violates architectural enforcement.
     */
    public static function detectViolation(string $lineText): ?string
    {
        $trimmed = trim($lineText);
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '#')) {
            return null;
        }

        foreach (self::getForbiddenPatterns() as $pattern) {
            if (str_contains($lineText, $pattern)) {
                return "Matched static pattern: '{$pattern}' in line: {$trimmed}";
            }
        }

        foreach (self::getForbiddenRegexPatterns() as $reg) {
            if (preg_match($reg, $lineText)) {
                return "Matched regex: '{$reg}' in line: {$trimmed}";
            }
        }

        return null;
    }

    /**
     * Enforce architectural integrity: Zero code outside JournalEngine is permitted
     * to instantiate JournalEntry or JournalEntryLine records directly.
     */
    public function test_no_code_outside_journal_engine_creates_journal_entries_or_lines()
    {
        $appPath = app_path();
        $violatingMatches = [];

        $directory = new \RecursiveDirectoryIterator($appPath);
        $iterator  = new \RecursiveIteratorIterator($directory);
        $regex     = new \RegexIterator($iterator, '/^.+\.php$/i', \RecursiveRegexIterator::GET_MATCH);

        foreach ($regex as $file) {
            $filePath = $file[0];
            $relativePath = str_replace(base_path() . '/', '', $filePath);

            // Allow only JournalEngine.php
            if (basename($filePath) === 'JournalEngine.php') {
                continue;
            }

            $content = file_get_contents($filePath);
            $lines = explode("\n", $content);

            foreach ($lines as $lineNum => $lineText) {
                $violation = self::detectViolation($lineText);
                if ($violation !== null) {
                    $violatingMatches[] = "{$relativePath}:" . ($lineNum + 1) . " -> {$violation}";
                }
            }
        }

        $this->assertEmpty(
            $violatingMatches,
            "Architectural violation: Code outside JournalEngine directly creates/inserts journal entries or lines:\n" . implode("\n", $violatingMatches)
        );
    }

    /**
     * Assert that the detector strictly catches DB::insert, DB::table insert, ::insert, forceCreate,
     * firstOrCreate, updateOrCreate, saveMany, new JournalEntryLine, ->save(), createMany, and query-builder variable inserts.
     */
    public function test_enforcement_detector_catches_all_forbidden_patterns()
    {
        $testCases = [
            "DB::table('journal_entries')->insert(['entry_no' => 'JE-01']);" => 'DB::table insert on journal_entries',
            "DB::table('journal_entry_lines')->insertGetId(['amount' => 500]);" => 'DB::table insertGetId on journal_entry_lines',
            "DB::table('journal_entries')->insertOrIgnore([...]);" => 'DB::table insertOrIgnore on journal_entries',
            "DB::insert('INSERT INTO journal_entries ...');" => 'DB::insert raw PDO call',
            "JournalEntry::insert(['entry_no' => 'JE-02']);" => 'Eloquent ::insert on JournalEntry',
            "JournalEntryLine::insert(['amount' => 100]);" => 'Eloquent ::insert on JournalEntryLine',
            "JournalEntry::forceCreate(['entry_no' => 'JE-03']);" => 'Eloquent forceCreate on JournalEntry',
            "JournalEntryLine::forceCreate(['amount' => 100]);" => 'Eloquent forceCreate on JournalEntryLine',
            "JournalEntry::firstOrCreate(['entry_no' => 'JE-04']);" => 'Eloquent firstOrCreate on JournalEntry',
            "JournalEntryLine::firstOrCreate(['account_id' => 1]);" => 'Eloquent firstOrCreate on JournalEntryLine',
            "JournalEntry::updateOrCreate(['entry_no' => 'JE-05'], [...]);" => 'Eloquent updateOrCreate on JournalEntry',
            "JournalEntryLine::updateOrCreate(['id' => 1], [...]);" => 'Eloquent updateOrCreate on JournalEntryLine',
            "\$line = new JournalEntryLine(['account_id' => 1]);" => 'new JournalEntryLine',
            "\$entry = new \App\Models\JournalEntry();" => 'new \App\Models\JournalEntry',
            "\$line->save();" => '->save() on journal line instance ($line)',
            "\$journalEntryLine->save();" => '->save() on journal line instance ($journalEntryLine)',
            "\$entry->lines()->createMany([['account_id' => 1]]);" => 'createMany on lines relation',
            "\$entry->lines()->insert([['account_id' => 1]]);" => 'insert on lines relation',
            "\$entry->lines()->saveMany([\$line1, \$line2]);" => 'saveMany on lines relation',
            "JournalEntryLine::createMany([['account_id' => 1]]);" => 'createMany on JournalEntryLine model',
            "JournalEntryLine::saveMany([\$l]);" => 'saveMany on JournalEntryLine model',
            "\$table->insert(['entry_no' => 'JE-VAR-01']);" => 'query-builder variable insert ($table)',
            "\$query->insertGetId(['amount' => 200]);" => 'query-builder variable insertGetId ($query)',
            "\$builder->insertOrIgnore(['amount' => 300]);" => 'query-builder variable insertOrIgnore ($builder)',
        ];

        foreach ($testCases as $codeSnippet => $label) {
            $violation = self::detectViolation($codeSnippet);
            $this->assertNotNull(
                $violation,
                "Detector failed to catch forbidden pattern [{$label}]: '{$codeSnippet}'"
            );
        }
    }
}
