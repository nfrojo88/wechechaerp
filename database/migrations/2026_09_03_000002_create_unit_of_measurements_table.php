<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('unit_of_measurements')) {
            Schema::create('unit_of_measurements', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name', 100);
                $table->text('description')->nullable();
                $table->boolean('is_system')->default(false);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });

            // Seed default units
            $defaultUnits = [
                ['code' => 'pcs',    'name' => 'Pieces',        'description' => 'Individual unit count',            'is_system' => true],
                ['code' => 'kg',     'name' => 'Kilogram',      'description' => 'Metric weight unit (1,000 g)',     'is_system' => true],
                ['code' => 'ton',    'name' => 'Metric Ton',    'description' => 'Heavy weight unit (1,000 kg)',     'is_system' => true],
                ['code' => 'm',      'name' => 'Meter',         'description' => 'Length measurement',               'is_system' => true],
                ['code' => 'm2',     'name' => 'Square Meter',  'description' => 'Area measurement (m²)',            'is_system' => true],
                ['code' => 'm3',     'name' => 'Cubic Meter',   'description' => 'Volume measurement (m³)',          'is_system' => true],
                ['code' => 'bag',    'name' => 'Bag',           'description' => 'Standard packaging bag (e.g. cement)', 'is_system' => true],
                ['code' => 'liter',  'name' => 'Liter',         'description' => 'Liquid volume measurement',        'is_system' => true],
                ['code' => 'roll',   'name' => 'Roll',          'description' => 'Rolled material',                  'is_system' => true],
                ['code' => 'set',    'name' => 'Set',           'description' => 'Set or assembly of items',         'is_system' => true],
                ['code' => 'pair',   'name' => 'Pair',          'description' => 'Pair of items (e.g. gloves, boots)', 'is_system' => true],
                ['code' => 'box',    'name' => 'Box',           'description' => 'Packaged box',                     'is_system' => true],
                ['code' => 'carton', 'name' => 'Carton',        'description' => 'Master carton packaging',          'is_system' => true],
                ['code' => 'sheet',  'name' => 'Sheet',         'description' => 'Sheet material (glass, iron, etc.)', 'is_system' => true],
                ['code' => 'bundle', 'name' => 'Bundle',        'description' => 'Bundled items (rebar, timber)',    'is_system' => true],
                ['code' => 'drum',   'name' => 'Drum',          'description' => 'Chemical / oil drum',               'is_system' => true],
                ['code' => 'pkt',    'name' => 'Packet',        'description' => 'Small packet',                     'is_system' => true],
                ['code' => 'trip',   'name' => 'Trip',          'description' => 'Transportation or delivery trip',  'is_system' => true],
                ['code' => 'hour',   'name' => 'Hour',          'description' => 'Operational or rental hour',       'is_system' => true],
            ];

            foreach ($defaultUnits as $u) {
                DB::table('unit_of_measurements')->insertOrIgnore([
                    'code'        => $u['code'],
                    'name'        => $u['name'],
                    'description' => $u['description'],
                    'is_system'   => $u['is_system'],
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_of_measurements');
    }
};
