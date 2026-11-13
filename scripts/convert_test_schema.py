import re
import os

def convert_file(rel_path):
    full_path = os.path.join(os.getcwd(), rel_path)
    with open(full_path, 'r') as f:
        content = f.read()

    # Check if already has ensureSchema
    if 'protected function ensureSchema' in content:
        print(f"Skipping {rel_path}: already has ensureSchema")
        return True

    m = re.search(r'protected function setUp\(\): void\s*\{(.*?)\n    \}', content, re.DOTALL)
    if not m:
        print(f"setUp not found in {rel_path}")
        return False

    setup_body = m.group(1)
    last_create = setup_body.rfind('Schema::create')
    if last_create == -1:
        print(f"Schema::create not found in {rel_path}")
        return False

    end_of_last_create = setup_body.find('});', last_create) + 3
    ddl_block = setup_body[:end_of_last_create].strip()
    after_ddl = setup_body[end_of_last_create:].strip()

    # Clean ddl_block of parent::setUp and DB checks
    ddl_statements = re.sub(r'parent::setUp\(\);\s*', '', ddl_block)
    ddl_statements = re.sub(r'if\s*\(DB::getDriverName\(\)[^;]+;\s*\}\s*', '', ddl_statements)
    ddl_statements = re.sub(r'if\s*\(DB::getDriverName\(\)[^;]+;\s*else\s*\{[^;]+;\s*\}\s*', '', ddl_statements)
    ddl_statements = ddl_statements.strip()

    created_tables = re.findall(r"Schema::create\('([^']+)'", ddl_statements)
    # Deduplicate while preserving order
    seen = set()
    deduped_tables = []
    for t in created_tables:
        if t not in seen:
            seen.add(t)
            deduped_tables.append(t)

    table_list_php = ", ".join([f"'{t}'" for t in deduped_tables])

    ensure_schema_func = f"""    protected function ensureSchema(): void
    {{
        $requiredTables = [{table_list_php}];
        $allExist = true;
        foreach ($requiredTables as $t) {{
            if (!Schema::hasTable($t)) {{
                $allExist = false;
                break;
            }}
        }}
        if ($allExist) {{
            return;
        }}

        {ddl_statements}
    }}"""

    new_setup = f"""    protected function setUp(): void
    {{
        parent::setUp();

        if (DB::getDriverName() === 'sqlite') {{
            DB::statement('PRAGMA foreign_keys = OFF;');
        }} else {{
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::statement('SET @gl_posting_in_progress = 1;');
            DB::statement('SET @period_lock_override = 0;');
        }}

        $this->ensureSchema();
        DB::beginTransaction();

        {after_ddl}
    }}

    protected function tearDown(): void
    {{
        DB::rollBack();
        parent::tearDown();
    }}"""

    old_setup_full = m.group(0)
    new_content = content.replace(old_setup_full, new_setup + "\n\n" + ensure_schema_func)

    with open(full_path, 'w') as f:
        f.write(new_content)

    print(f"Successfully converted {rel_path} with {len(deduped_tables)} tables: {deduped_tables}")
    return True

if __name__ == '__main__':
    targets = [
        'tests/Unit/Finance/TrialBalanceIntegrityTest.php',
        'tests/Unit/Finance/JournalImmutabilityAndReversalTest.php',
        'tests/Unit/Finance/OpeningBalanceLockedVoucherTest.php',
        'tests/Unit/Finance/FixedAssetDepreciationTest.php',
        'tests/Unit/Finance/PrepaidBondAmortizationTest.php',
        'tests/Unit/Finance/AccrualAndAutoReversalTest.php',
        'tests/Unit/Finance/PayrollGlPostingTest.php',
        'tests/Unit/Finance/PettyCashSubledgerReconciliationTest.php',
        'tests/Unit/Finance/TaxConfigAndRemittanceTest.php',
        'tests/Unit/Finance/ThreeWayMatchPurchaseFlowTest.php',
        'tests/Unit/Finance/BackfillDryRunTest.php',
        'tests/Unit/Finance/CrossReportConsistencyTest.php',
        'tests/Unit/Finance/UpstreamPostingBugFixesTest.php'
    ]
    for t in targets:
        convert_file(t)
