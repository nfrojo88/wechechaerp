<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class UnitOfMeasurement extends Model
{
    use HasFactory;

    protected $table = 'unit_of_measurements';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_system',
        'created_by',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all units with graceful fallback if table does not exist
     */
    public static function allUnits()
    {
        try {
            if (Schema::hasTable('unit_of_measurements')) {
                $units = static::orderBy('name')->get();
                if ($units->isNotEmpty()) {
                    return $units;
                }
            }
        } catch (\Throwable $e) {}

        return collect([
            (object)['id' => 1, 'code' => 'pcs',    'name' => 'Pieces',        'description' => 'Individual unit count', 'is_system' => true],
            (object)['id' => 2, 'code' => 'kg',     'name' => 'Kilogram',      'description' => 'Metric weight unit',     'is_system' => true],
            (object)['id' => 3, 'code' => 'ton',    'name' => 'Metric Ton',    'description' => 'Heavy weight unit',     'is_system' => true],
            (object)['id' => 4, 'code' => 'm',      'name' => 'Meter',         'description' => 'Length measurement',    'is_system' => true],
            (object)['id' => 5, 'code' => 'm2',     'name' => 'Square Meter',  'description' => 'Area measurement',      'is_system' => true],
            (object)['id' => 6, 'code' => 'm3',     'name' => 'Cubic Meter',   'description' => 'Volume measurement',    'is_system' => true],
            (object)['id' => 7, 'code' => 'bag',    'name' => 'Bag',           'description' => 'Packaging bag',         'is_system' => true],
            (object)['id' => 8, 'code' => 'liter',  'name' => 'Liter',         'description' => 'Liquid volume',         'is_system' => true],
            (object)['id' => 9, 'code' => 'roll',   'name' => 'Roll',          'description' => 'Rolled material',       'is_system' => true],
            (object)['id' => 10, 'code' => 'set',   'name' => 'Set',           'description' => 'Set / assembly',        'is_system' => true],
            (object)['id' => 11, 'code' => 'pair',  'name' => 'Pair',          'description' => 'Pair of items',         'is_system' => true],
            (object)['id' => 12, 'code' => 'box',   'name' => 'Box',           'description' => 'Packaged box',          'is_system' => true],
            (object)['id' => 13, 'code' => 'carton', 'name' => 'Carton',       'description' => 'Master carton',         'is_system' => true],
            (object)['id' => 14, 'code' => 'sheet', 'name' => 'Sheet',         'description' => 'Sheet material',        'is_system' => true],
            (object)['id' => 15, 'code' => 'bundle', 'name' => 'Bundle',       'description' => 'Bundled items',         'is_system' => true],
            (object)['id' => 16, 'code' => 'drum',   'name' => 'Drum',         'description' => 'Chemical / oil drum',   'is_system' => true],
            (object)['id' => 17, 'code' => 'pkt',    'name' => 'Packet',       'description' => 'Small packet',          'is_system' => true],
            (object)['id' => 18, 'code' => 'trip',   'name' => 'Trip',         'description' => 'Delivery trip',         'is_system' => true],
            (object)['id' => 19, 'code' => 'hour',   'name' => 'Hour',         'description' => 'Operational hour',      'is_system' => true],
        ]);
    }
}
