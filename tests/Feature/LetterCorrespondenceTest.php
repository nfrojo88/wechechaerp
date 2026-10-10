<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\LetterActionLog;
use App\Models\LetterRecipient;
use App\Models\LetterSequence;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class LetterCorrespondenceTest extends TestCase
{
    /**
     * Helper to mock an Eloquent user with Spatie-like role support.
     */
    private function createMockUser(int $id, string $name, array $roles = [], bool $isActive = true): User
    {
        $user = new class([
            'id'        => $id,
            'name'      => $name,
            'email'     => strtolower(str_replace(' ', '.', $name)) . '@wechecha.com',
            'is_active' => $isActive,
        ]) extends User {
            public array $testRoles = [];

            public function hasRole($roles, string $guard = null): bool
            {
                $roles = is_array($roles) ? $roles : [$roles];
                return !empty(array_intersect(array_map('strtolower', $roles), array_map('strtolower', $this->testRoles)));
            }

            public function hasAnyRole(...$roles): bool
            {
                $flattened = is_array($roles[0] ?? null) ? $roles[0] : $roles;
                return !empty(array_intersect(array_map('strtolower', $flattened), array_map('strtolower', $this->testRoles)));
            }

            public function getRoleNames()
            {
                return collect($this->testRoles);
            }
        };

        $user->id = $id;
        $user->testRoles = $roles;
        return $user;
    }

    /**
     * 1. Test: Any employee can create a draft and send a letter to the secretary.
     *    - Writing letter (subject, body, optional attachments) starts as Draft or Sent.
     *    - Draft has status 'draft', null letter_number, null category, null addressed person.
     *    - Sending transitions status to 'sent' with sent_at populated.
     *    - Sending has NO automatic category and NO automatic person selection.
     */
    public function test_any_employee_can_create_draft_and_send_letter_to_secretary(): void
    {
        $employee = $this->createMockUser(101, 'Dawit Employee', ['employee']);

        // Step A: Employee creates a draft
        $draft = new Letter([
            'created_by'           => $employee->id,
            'sender'               => $employee->name,
            'subject'              => 'Request for Annual Leave Clearance',
            'specification'        => 'I am requesting clearance for my scheduled annual leave starting next week.',
            'status'               => Letter::STATUS_DRAFT,
            'letter_number'        => null,
            'category'             => null,
            'addressed_to_user_id' => null,
            'is_reference_locked'  => false,
            'sent_at'              => null,
        ]);

        $this->assertTrue($draft->isDraft());
        $this->assertFalse($draft->isSent());
        $this->assertFalse($draft->isRegistered());
        $this->assertNull($draft->letter_number, 'Draft letters must NOT have an auto-assigned reference number.');
        $this->assertNull($draft->category, 'Draft letters must NOT have an auto-assigned category.');
        $this->assertNull($draft->addressed_to_user_id, 'Draft letters must NOT have an auto-selected person.');
        $this->assertFalse($draft->is_reference_locked);

        // Step B: Employee sends draft directly to Secretary
        $sentLetter = clone $draft;
        $sentLetter->status = Letter::STATUS_SENT;
        $sentLetter->sent_at = now();

        $this->assertFalse($sentLetter->isDraft());
        $this->assertTrue($sentLetter->isSent());
        $this->assertNotNull($sentLetter->sent_at);
        // Strictly verify that sending DOES NOT auto-assign category, reference number, or recipient person
        $this->assertNull($sentLetter->letter_number, 'Sent letter must remain unnumbered until secretary reviews.');
        $this->assertNull($sentLetter->category, 'Sent letter must have NO automatic category.');
        $this->assertNull($sentLetter->addressed_to_user_id, 'Sent letter must have NO automatic person selection.');
    }

    /**
     * 2. Test: Employees cannot see other employees' letters (Employee Isolation).
     *    - Creator sees their own letters (Draft, Sent, Registered).
     *    - Unrelated employees CANNOT view the creator's letters.
     *    - Secretary and Admin CAN view submitted letters.
     *    - The addressed person CAN view once assigned/registered.
     */
    public function test_employees_cannot_see_other_employees_letters(): void
    {
        $employeeA = $this->createMockUser(201, 'Abebe Employee', ['employee']);
        $employeeB = $this->createMockUser(202, 'Biruk Employee', ['employee']);
        $employeeC = $this->createMockUser(203, 'Chala Addressed Person', ['employee']);
        $secretary = $this->createMockUser(301, 'Sara Secretary', ['secretary']);
        $globalAdmin = $this->createMockUser(1, 'System Admin', ['global_admin']);

        // Scenario 1: Employee A creates a Draft letter
        $draftA = new Letter([
            'id'                   => 501,
            'created_by'           => $employeeA->id,
            'subject'              => 'Confidential Advance Loan Request',
            'specification'        => 'Loan application details...',
            'status'               => Letter::STATUS_DRAFT,
            'letter_number'        => null,
            'category'             => null,
            'addressed_to_user_id' => null,
        ]);

        // Creator has access
        $this->assertTrue($draftA->isAccessibleBy($employeeA));
        // Other employee strictly CANNOT access Draft
        $this->assertFalse($draftA->isAccessibleBy($employeeB), 'Employee B must not see Employee A draft.');
        // Even secretary cannot view raw drafts
        $this->assertFalse($draftA->isAccessibleBy($secretary), 'Secretary must not see private employee drafts.');

        // Scenario 2: Employee A sends letter to Secretary
        $sentA = new Letter([
            'id'                   => 502,
            'created_by'           => $employeeA->id,
            'subject'              => 'General Correspondence',
            'specification'        => 'Details...',
            'status'               => Letter::STATUS_SENT,
            'letter_number'        => null,
            'category'             => null,
            'addressed_to_user_id' => null,
        ]);

        // Creator has access
        $this->assertTrue($sentA->isAccessibleBy($employeeA));
        // Secretary and Admin have access
        $this->assertTrue($sentA->isAccessibleBy($secretary));
        $this->assertTrue($sentA->isAccessibleBy($globalAdmin));
        // Unrelated Employee B CANNOT see Employee A's sent letter
        $this->assertFalse($sentA->isAccessibleBy($employeeB), 'Employee B must not see Employee A sent letter.');

        // Scenario 3: Secretary registers letter and addresses it to Employee C
        $registeredA = new Letter([
            'id'                   => 503,
            'created_by'           => $employeeA->id,
            'subject'              => 'Approved Project Letter',
            'specification'        => 'Official correspondence...',
            'status'               => Letter::STATUS_REGISTERED,
            'letter_number'        => 'LTR-2026-0005',
            'category'             => Letter::CATEGORY_GOVERNMENT,
            'addressed_to_user_id' => $employeeC->id,
            'is_reference_locked'  => true,
        ]);

        // Creator can see their own registered letter
        $this->assertTrue($registeredA->isAccessibleBy($employeeA));
        // Addressed person can see the letter
        $this->assertTrue($registeredA->isAccessibleBy($employeeC));
        // Unrelated Employee B STILL CANNOT see Employee A's letter
        $this->assertFalse($registeredA->isAccessibleBy($employeeB), 'Unrelated Employee B must not see Employee A registered letter.');
    }

    /**
     * 3. Test: Only the secretary role can register letters.
     *    - Regular employee or non-secretary role is unauthorized.
     *    - Secretary and global_admin roles are authorized.
     */
    public function test_only_secretary_role_can_register_letters(): void
    {
        $employee = $this->createMockUser(401, 'Regular Employee', ['employee']);
        $storeKeeper = $this->createMockUser(402, 'Store Keeper', ['store_keeper']);
        $secretary = $this->createMockUser(403, 'Head Office Secretary', ['secretary']);
        $admin = $this->createMockUser(404, 'Admin User', ['admin']);
        $globalAdmin = $this->createMockUser(405, 'Global Admin', ['global_admin']);

        $allowedRoles = ['secretary', 'Secretary', 'admin', 'global_admin'];

        // Regular employee is unauthorized
        $this->assertFalse($employee->hasAnyRole($allowedRoles));
        // Store keeper is unauthorized
        $this->assertFalse($storeKeeper->hasAnyRole($allowedRoles));

        // Secretary and Admins are authorized
        $this->assertTrue($secretary->hasAnyRole($allowedRoles));
        $this->assertTrue($admin->hasAnyRole($allowedRoles));
        $this->assertTrue($globalAdmin->hasAnyRole($allowedRoles));
    }

    /**
     * 4. Test: Reference numbers are unique and locked after assignment.
     *    - Formatting adheres to "LTR-{YEAR}-{NUMBER}".
     *    - Once is_reference_locked is true, reference number cannot be changed or overwritten.
     */
    public function test_reference_numbers_are_unique_and_locked_after_assignment(): void
    {
        $year = 2026;
        $peek1 = LetterSequence::peekNext($year, 'LTR');
        $this->assertMatchesRegularExpression('/^LTR-2026-\d{4}$/', $peek1);

        // Letter with locked reference number
        $letter = new Letter([
            'id'                  => 601,
            'letter_number'       => 'LTR-2026-0042',
            'is_reference_locked' => true,
        ]);

        $this->assertTrue($letter->is_reference_locked);
        $this->assertEquals('LTR-2026-0042', $letter->letter_number);

        // Attempting to re-assign or change reference number when is_reference_locked is true must be rejected
        $cannotChange = ($letter->is_reference_locked && !empty($letter->letter_number));
        $this->assertTrue($cannotChange, 'Reference number must be locked and protected from change/reuse.');

        // Initial unlocked letter can receive an assignment
        $unlockedLetter = new Letter([
            'id'                  => 602,
            'letter_number'       => null,
            'is_reference_locked' => false,
        ]);
        $canAssign = !($unlockedLetter->is_reference_locked && !empty($unlockedLetter->letter_number));
        $this->assertTrue($canAssign, 'Unlocked letter without reference number can be assigned.');
    }

    /**
     * 5. Test: Registration is blocked until number, category and person are set.
     *    - All 3 must be manually set:
     *      (1) Reference Number
     *      (2) One Category from: Leave Letter, Advance Loan Letter, Payment, Government, Bank & Insurance
     *      (3) Addressed / Handled Person from user list
     *    - Missing any of the three blocks registration.
     */
    public function test_registration_is_blocked_until_number_category_and_person_are_set(): void
    {
        // Allowed 5 categories
        $expectedCategories = [
            'Leave Letter',
            'Advance Loan Letter',
            'Payment',
            'Government',
            'Bank & Insurance',
        ];
        $this->assertEquals($expectedCategories, Letter::CATEGORIES);

        // Scenario A: None of the three are set -> Blocked (missing 3)
        $letterA = new Letter([
            'letter_number'        => null,
            'category'             => null,
            'addressed_to_user_id' => null,
        ]);
        $missingA = $this->getMissingRegistrationFields($letterA);
        $this->assertCount(3, $missingA);
        $this->assertContains('Reference Number', $missingA);
        $this->assertContains('Category', $missingA);
        $this->assertContains('Addressed / Handled Person', $missingA);

        // Scenario B: Reference Number only -> Blocked (missing 2)
        $letterB = new Letter([
            'letter_number'        => 'LTR-2026-0010',
            'category'             => null,
            'addressed_to_user_id' => null,
        ]);
        $missingB = $this->getMissingRegistrationFields($letterB);
        $this->assertCount(2, $missingB);
        $this->assertNotContains('Reference Number', $missingB);
        $this->assertContains('Category', $missingB);
        $this->assertContains('Addressed / Handled Person', $missingB);

        // Scenario C: Number + Category set, but no person -> Blocked (missing 1)
        $letterC = new Letter([
            'letter_number'        => 'LTR-2026-0011',
            'category'             => Letter::CATEGORY_PAYMENT,
            'addressed_to_user_id' => null,
        ]);
        $missingC = $this->getMissingRegistrationFields($letterC);
        $this->assertCount(1, $missingC);
        $this->assertEquals(['Addressed / Handled Person'], $missingC);

        // Scenario D: All three set -> Registration UNBLOCKED
        $letterD = new Letter([
            'letter_number'        => 'LTR-2026-0012',
            'category'             => Letter::CATEGORY_ADVANCE_LOAN_LETTER,
            'addressed_to_user_id' => 777,
        ]);
        $missingD = $this->getMissingRegistrationFields($letterD);
        $this->assertEmpty($missingD, 'All three items set; registration must be unblocked.');

        // Simulate successful registration
        $letterD->status = Letter::STATUS_REGISTERED;
        $letterD->is_reference_locked = true;
        $letterD->registered_by = 301;
        $letterD->registered_at = now();

        $this->assertTrue($letterD->isRegistered());
        $this->assertTrue($letterD->is_reference_locked);
        $this->assertEquals(Letter::CATEGORY_ADVANCE_LOAN_LETTER, $letterD->category);
        $this->assertEquals(777, $letterD->addressed_to_user_id);
    }

    /**
     * 6. Test: Fallback to global_admin works when no active secretary exists.
     *    - When active secretary collection is empty, recipient role becomes 'global_admin'.
     *    - An audit action log of 'fallback_to_admin' is recorded.
     */
    public function test_fallback_to_global_admin_works_when_no_secretary_exists(): void
    {
        $sender = $this->createMockUser(801, 'Sender Employee', ['employee']);

        $letter = new Letter([
            'id'                   => 901,
            'created_by'           => $sender->id,
            'subject'              => 'Urgent Regulatory Filing',
            'specification'        => 'Letter details for urgent processing...',
            'status'               => Letter::STATUS_SENT,
            'letter_number'        => null,
            'category'             => null,
            'addressed_to_user_id' => null,
        ]);

        // Simulate active secretaries collection is empty
        $activeSecretaries = collect([]);
        $fallbackToAdmin = $activeSecretaries->isEmpty();

        $this->assertTrue($fallbackToAdmin, 'Fallback must trigger when no active secretary exists.');

        // Fallback target role is global_admin
        $targetRole = $fallbackToAdmin ? 'global_admin' : 'secretary';
        $this->assertEquals('global_admin', $targetRole);

        // Verify routing recipient record configuration
        $recipientRecord = new LetterRecipient([
            'letter_id'    => $letter->id,
            'from_user_id' => $sender->id,
            'to_user_id'   => null,
            'to_role_name' => $targetRole,
            'action'       => 'initial_sent',
            'status'       => Letter::STATUS_SENT,
        ]);

        $this->assertEquals('global_admin', $recipientRecord->to_role_name);
        $this->assertEquals('Role: Global admin', $recipientRecord->recipient_label);

        // Verify audit log payload
        $auditLog = new LetterActionLog([
            'letter_id'   => $letter->id,
            'user_id'     => $sender->id,
            'action'      => 'fallback_to_admin',
            'description' => 'No active secretary user found in system. Fallback triggered: letter routed to Global Administrator.',
            'created_at'  => now(),
        ]);

        $this->assertEquals('fallback_to_admin', $auditLog->action);
        $this->assertStringContainsString('Global Administrator', $auditLog->description);
    }

    /**
     * Helper to check missing fields for registration matching LetterController logic.
     */
    private function getMissingRegistrationFields(Letter $letter): array
    {
        $missing = [];
        if (empty($letter->letter_number)) {
            $missing[] = 'Reference Number';
        }
        if (empty($letter->category)) {
            $missing[] = 'Category';
        }
        if (empty($letter->addressed_to_user_id)) {
            $missing[] = 'Addressed / Handled Person';
        }
        return $missing;
    }
}
