<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if (file_exists(app_path('Helpers/helpers.php'))) {
            require_once app_path('Helpers/helpers.php');
        }

        $this->app->bind(\App\Contracts\SmsProviderInterface::class, function ($app) {
            $driver = config('procurement_handoffs.default_provider', env('SMS_DRIVER', 'afromessage'));
            return match (strtolower($driver)) {
                'africastalking', 'at'       => new \App\Services\Sms\AfricasTalkingProvider(),
                'log', 'simulate', 'testing' => new \App\Services\Sms\LogSmsProvider(),
                default                      => new \App\Services\Sms\AfroMessageProvider(),
            };
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        \Illuminate\Pagination\Paginator::useBootstrap();

        // Custom Blade directive for selected (ensures compatibility on all Laravel versions)
        \Illuminate\Support\Facades\Blade::directive('selected', function ($expression) {
            return "<?php if ({$expression}) echo 'selected'; ?>";
        });

        // Ensure centralized public/uploads directory exists on server
        if (!file_exists(public_path('uploads'))) {
            @mkdir(public_path('uploads'), 0755, true);
        }

        // Auto-run pending migrations on each request in production (cPanel deployment)
        // This ensures the database schema is always up-to-date without manual artisan commands
        $this->runAutoMigrations();

        // Register Procurement Lifecycle Status Transition Observers
        \App\Models\PurchaseRequest::observe(\App\Observers\PurchaseRequestObserver::class);
        \App\Models\MaterialRequest::observe(\App\Observers\MaterialRequestObserver::class);
    }

    /**
     * Automatically run pending migrations.
     * Safely checks for changes to avoid performance overhead on normal requests.
     */
    protected function runAutoMigrations()
    {
        try {
            // Only run if connected to DB successfully
            DB::connection()->getPdo();

            $lockFile = storage_path('app/migration.lock');
            $migrationsPath = database_path('migrations');
            $migrationFiles = glob($migrationsPath . '/*.php') ?: [];

            // Signature based on file count, names, and newest file modification time
            $latestMigrationTime = 0;
            foreach ($migrationFiles as $file) {
                $mtime = filemtime($file);
                if ($mtime > $latestMigrationTime) {
                    $latestMigrationTime = $mtime;
                }
            }
            $currentSignature = md5(count($migrationFiles) . '_' . $latestMigrationTime . '_' . implode(',', array_map('basename', $migrationFiles)));
            $savedSignature = file_exists($lockFile) ? trim(file_get_contents($lockFile)) : '';

            $shouldMigrate = false;

            if ($currentSignature !== $savedSignature) {
                $shouldMigrate = true;
            } elseif (!Schema::hasTable('migrations') || !Schema::hasTable('expense_hub_vat_decisions') || !Schema::hasTable('vehicle_km_logs') || !Schema::hasTable('vehicle_fuel_requests') || !Schema::hasTable('notification_log') || !Schema::hasTable('procurement_sms_settings')) {
                $shouldMigrate = true;
            }

            if ($shouldMigrate) {
                Artisan::call('migrate', [
                    '--force'          => true,   // required in production
                    '--no-interaction' => true,
                ]);

                // Run seeders automatically to ensure admin user, roles, and settings exist
                Artisan::call('db:seed', [
                    '--class' => 'Database\Seeders\RolesAndPermissionsSeeder',
                    '--force' => true,
                ]);
                Artisan::call('db:seed', [
                    '--class' => 'Database\Seeders\AdminUserSeeder',
                    '--force' => true,
                ]);
                Artisan::call('db:seed', [
                    '--class' => 'Database\Seeders\ProcurementSmsSettingsSeeder',
                    '--force' => true,
                ]);

                // Update lock file with the new signature
                @file_put_contents($lockFile, $currentSignature);

                Log::info('Auto-migration completed successfully at ' . now());
            }
        } catch (\Exception $e) {
            // Never crash the app due to migration failure — just log it
            Log::error('Auto-migration failed: ' . $e->getMessage());
        }
    }
}
