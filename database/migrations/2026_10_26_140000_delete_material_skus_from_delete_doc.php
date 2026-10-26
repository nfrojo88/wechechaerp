<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Permanently delete or archive material SKU codes requested in delete.doc.
     */
    public function up(): void
    {
        $skusToDelete = [
            'CHI-67-652',
            'CHI-68-777',
            'FA-117B',
            'FA-402B',
            'FA-BALD',
            'FA-BLA3',
            'FA-BLA4',
            'FA-BLA6',
            'FA-BLA8',
            'FA-BMTT',
            'FA-DAMP',
            'FA-DOU2',
            'FA-GEN3',
            'FA-HDP2',
            'FA-HDP3',
            'FA-HDPE',
            'FA-LAST',
            'FA-MAG2',
            'FA-MAN3',
            'FA-NAI2',
            'FA-NAI3',
            'FA-PORC',
            'FA-PPR2',
            'FA-PPR3',
            'FA-PPR4',
            'FA-PPR5',
            'FA-PPR6',
            'FA-PPR7',
            'FA-PPR8',
            'FA-PPR9',
            'FA-PPRP',
            'FA-PREC',
            'FA-PVC2',
            'FA-PVCP',
            'FA-SEG2',
            'FA-SHA2',
            'FA-SKIR',
            'FA-SPAC',
            'FA-SPIR',
            'FA-TIM1',
            'FA-TIM2',
            'FA-TVAR',
            'FA-WAT11',
            'FA-WAT2',
            'FA-WAT6',
            'FA-WIRE',
            'FA-YPPC',
            'JER-201-540',
            'MAT-0037',
            'MAT-0040',
            'MAT-0080',
            'MAT-0081',
            'MAT-0082',
            'MAT-0085',
            'MAT-0086',
            'MAT-0140',
            'MAT-0183',
            'MAT-0263',
            'MAT-0283',
            'MAT-0284',
            'MAT-0285',
            'MAT-0286',
            'MAT-0287',
            'MAT-0482',
            'MAT-053',
            'MAT-0535',
            'MAT-0537',
            'MAT-0569',
            'MAT-0581',
            'MAT-0584',
            'MAT-0658',
            'MAT-0668',
            'MAT-0678',
            'MAT-0774',
            'MAT-0860',
            'MAT-0861',
            'MAT-0862',
            'MAT-0863',
            'MAT-0865',
            'MAT-0867',
            'MAT-0868',
            'MAT-1175',
            'MAT-1208',
        ];

        // 1. Delete associated FixedAssets and Units if matching FA- prefix
        if (Schema::hasTable('fixed_assets')) {
            $faPrefixes = [];
            foreach ($skusToDelete as $sku) {
                if (str_starts_with(strtoupper($sku), 'FA-')) {
                    $prefix = substr($sku, 3);
                    $faPrefixes[] = $prefix;
                    $faPrefixes[] = strtolower($prefix);
                    $faPrefixes[] = $sku;
                    $faPrefixes[] = strtolower($sku);
                }
            }

            if (!empty($faPrefixes)) {
                $matchingFa = DB::table('fixed_assets')->where(function ($q) use ($faPrefixes) {
                    $q->whereIn('code_prefix', $faPrefixes)
                      ->orWhereIn(DB::raw('LOWER(code_prefix)'), array_map('strtolower', $faPrefixes));
                })->get();

                foreach ($matchingFa as $fa) {
                    if (Schema::hasTable('fixed_asset_units')) {
                        DB::table('fixed_asset_units')->where('fixed_asset_id', $fa->id)->delete();
                    }
                    if (Schema::hasTable('fixed_asset_assignments')) {
                        DB::table('fixed_asset_assignments')->where('fixed_asset_id', $fa->id)->delete();
                    }
                    try {
                        DB::table('fixed_assets')->where('id', $fa->id)->delete();
                    } catch (\Throwable $e) {
                        if (Schema::hasColumn('fixed_assets', 'deleted_at')) {
                            DB::table('fixed_assets')->where('id', $fa->id)->update(['deleted_at' => now()]);
                        }
                    }
                }
            }
        }

        // 2. Delete / Archive Products matching these SKUs
        if (Schema::hasTable('products')) {
            $lowerSkus = array_map('strtolower', $skusToDelete);

            $matchingProducts = DB::table('products')
                ->where(function ($q) use ($skusToDelete, $lowerSkus) {
                    $q->whereIn('sku', $skusToDelete)
                      ->orWhereIn(DB::raw('LOWER(TRIM(sku))'), $lowerSkus);
                })
                ->get();

            foreach ($matchingProducts as $prod) {
                if (Schema::hasTable('inventories')) {
                    DB::table('inventories')->where('product_id', $prod->id)->delete();
                }
                if (Schema::hasTable('rebar_dia_products')) {
                    DB::table('rebar_dia_products')->where('product_id', $prod->id)->delete();
                }
                if (Schema::hasTable('material_prices')) {
                    DB::table('material_prices')->where('product_id', $prod->id)->delete();
                }

                try {
                    DB::table('products')->where('id', $prod->id)->delete();
                } catch (\Throwable $e) {
                    $update = [];
                    if (Schema::hasColumn('products', 'deleted_at')) {
                        $update['deleted_at'] = now();
                    }
                    if (Schema::hasColumn('products', 'sku')) {
                        $update['sku'] = $prod->sku . '_DELETED_' . time() . '_' . $prod->id;
                    }
                    if (!empty($update)) {
                        DB::table('products')->where('id', $prod->id)->update($update);
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Deletions cannot be reversed without backups
    }
};
