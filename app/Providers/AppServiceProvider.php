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
    }

    /**
     * Automatically run pending migrations.
     * Safely checks for a lock file to avoid running on every request.
     */
    protected function runAutoMigrations()
    {
        try {
            // Only run if connected to DB successfully
            DB::connection()->getPdo();

            // Use a lock file to prevent running on every request
            $lockFile = storage_path('app/migration.lock');
            $migrationsPath = database_path('migrations');

            // Get the latest migration file timestamp
            $latestMigration = collect(glob($migrationsPath . '/*.php'))
                ->map(fn($f) => filemtime($f))
                ->max();

            // Read last migration time from lock file
            $lastRun = file_exists($lockFile) ? (int) file_get_contents($lockFile) : 0;

            // Only run if there are new migration files since last run
            if ($latestMigration > $lastRun) {
                Artisan::call('migrate', [
                    '--force' => true,   // required in production
                    '--no-interaction' => true,
                ]);

                // Run seeders automatically to ensure admin user and roles exist
                Artisan::call('db:seed', [
                    '--class' => 'Database\Seeders\RolesAndPermissionsSeeder',
                    '--force' => true,
                ]);
                Artisan::call('db:seed', [
                    '--class' => 'Database\Seeders\AdminUserSeeder',
                    '--force' => true,
                ]);

                // Update the lock file with current timestamp
                file_put_contents($lockFile, time());

                Log::info('Auto-migration completed at ' . now());
            }
        } catch (\Exception $e) {
            // Never crash the app due to migration failure — just log it
            Log::error('Auto-migration failed: ' . $e->getMessage());
        }
    }
}
