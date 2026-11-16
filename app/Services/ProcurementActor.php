<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class ProcurementActor
{
    /**
     * Resolve the current actor: Auth::user() if authenticated,
     * otherwise the configured system user.
     * Throws an exception if neither exists.
     *
     * @throws RuntimeException
     */
    public static function resolve(): User
    {
        if ($user = Auth::user()) {
            return $user;
        }

        $systemUserId = config('procurement.system_user_id');
        if ($systemUserId) {
            $systemUser = User::find($systemUserId);
            if ($systemUser) {
                return $systemUser;
            }
        }

        throw new RuntimeException('No authenticated user or configured procurement system user (procurement.system_user_id) found.');
    }

    /**
     * Resolve the ID of the current actor.
     *
     * @throws RuntimeException
     */
    public static function id(): int
    {
        return (int) self::resolve()->id;
    }
}
