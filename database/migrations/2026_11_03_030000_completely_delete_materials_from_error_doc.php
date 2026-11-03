<?php

use Illuminate\Database\Migrations\Migration;
use App\Services\ProductPurgeService;

return new class extends Migration
{
    /**
     * Complete and permanent deletion of material/product SKUs listed in error.doc.
     * Cascades through fixed assets, units, assignments, inventory, movements,
     * purchase/transfer/receipt line items, and references.
     */
    public function up(): void
    {
        $skus = [
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
            'FA-Shar',
            'FA-SKIR',
            'FA-SPAC',
            'FA-SPIR',
            'FA-TIM1',
            'FA-TIM2',
            'FA-TVAR',
            'FA-WAT2',
            'FA-WAT6',
            'FA-WAT11',
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
            'Mat-0860',
            'MAT-0861',
            'MAT-0862',
            'MAT-0863',
            'MAT-0865',
            'MaT-0867',
            'Mat-0868',
            'MAT-1175',
            'MAT-1208',
        ];

        try {
            $purgeService = app(ProductPurgeService::class);
            $purgeService->purgeSkus($skus);
        } catch (\Throwable $e) {
            // Log and allow migration chain to complete safely
            \Illuminate\Support\Facades\Log::error("Migration completely_delete_materials_from_error_doc failed: " . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Deletions are permanent and cannot be reversed
    }
};
