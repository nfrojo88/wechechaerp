<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProcurementSmsSetting;

class ProcurementSmsSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $handoffs = config('procurement_handoffs.handoffs', []);

        foreach ($handoffs as $key => $config) {
            ProcurementSmsSetting::firstOrCreate(
                ['handoff_key' => $key],
                [
                    'name'        => $config['name'] ?? $key,
                    'sender_role' => $config['sender_role'] ?? 'user',
                    'target_role' => implode(',', (array)($config['target_roles'] ?? ['user'])),
                    'is_enabled'  => true,
                    'template'    => $config['default_template'] ?? '{priority}{req_no} from {sender_name}: {action}. Open: {link}',
                    'description' => $config['description'] ?? null,
                ]
            );
        }
    }
}
