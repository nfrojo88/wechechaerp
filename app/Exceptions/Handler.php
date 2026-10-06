<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use App\Services\ItIncidentAlertService;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            // Automatically alert GM or Global Admin if a severe unhandled 500 error occurs
            if (
                !($e instanceof ValidationException) &&
                !($e instanceof AuthenticationException) &&
                !($e instanceof AuthorizationException) &&
                !($e instanceof HttpException)
            ) {
                try {
                    $alertService = app(ItIncidentAlertService::class);
                    $url = request()->fullUrl() ?? 'Background/CLI';
                    $alertService->sendDirectSystemErrorAlert(
                        class_basename($e),
                        $e->getMessage(),
                        $url
                    );
                } catch (\Throwable $ignored) {
                    // Suppress alert service failure to preserve standard error reporting
                }
            }
        });
    }
}
