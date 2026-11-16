<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\ProcurementActor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use RuntimeException;
use Tests\TestCase;

class ProcurementActorHelperTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Auth::logout();
    }

    protected function tearDown(): void
    {
        Auth::logout();
        parent::tearDown();
    }

    /**
     * Test 1: Helper returns the authenticated user if logged in.
     */
    public function test_helper_returns_authenticated_user_when_present(): void
    {
        $authUser = User::firstOrCreate(
            ['email' => 'auth_actor_test@wechecha.local'],
            ['name' => 'Authenticated Actor', 'password' => bcrypt('password')]
        );

        $systemUser = User::firstOrCreate(
            ['email' => 'system_actor_test@wechecha.local'],
            ['name' => 'System Actor', 'password' => bcrypt('password')]
        );
        Config::set('procurement.system_user_id', $systemUser->id);

        Auth::login($authUser);

        $resolved = ProcurementActor::resolve();

        $this->assertEquals($authUser->id, $resolved->id);
        $this->assertEquals($authUser->email, $resolved->email);
        $this->assertEquals($authUser->id, ProcurementActor::id());
    }

    /**
     * Test 2: Helper returns the configured system user when there is no authenticated user.
     */
    public function test_helper_returns_configured_system_user_when_unauthenticated(): void
    {
        $systemUser = User::firstOrCreate(
            ['email' => 'system_actor_test@wechecha.local'],
            ['name' => 'System Actor', 'password' => bcrypt('password')]
        );
        Config::set('procurement.system_user_id', $systemUser->id);

        Auth::logout();
        $this->assertNull(Auth::user());

        $resolved = ProcurementActor::resolve();

        $this->assertEquals($systemUser->id, $resolved->id);
        $this->assertEquals($systemUser->email, $resolved->email);
        $this->assertEquals($systemUser->id, ProcurementActor::id());
    }

    /**
     * Test 3: Helper throws a clear RuntimeException when neither authenticated user nor system user exists.
     */
    public function test_helper_throws_exception_when_neither_authenticated_user_nor_system_user_exists(): void
    {
        Auth::logout();
        Config::set('procurement.system_user_id', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No authenticated user or configured procurement system user');

        ProcurementActor::resolve();
    }

    /**
     * Test 4: Helper throws a clear RuntimeException when configured system user ID does not exist in database.
     */
    public function test_helper_throws_exception_when_configured_system_user_id_is_invalid(): void
    {
        Auth::logout();
        Config::set('procurement.system_user_id', 999999999);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No authenticated user or configured procurement system user');

        ProcurementActor::resolve();
    }
}
