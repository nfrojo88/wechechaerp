<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'iclock/*',
        'iclock/cdata',
        'iclock/cdata.php',
        'iclock/getrequest',
        'iclock/getrequest.php',
        'iclock/devicecmd',
        'iclock/devicecmd.php',
        'iclock/fdata',
        'iclock/push',
        'api/iclock/*',
        'api/biometric/*',
    ];
}
