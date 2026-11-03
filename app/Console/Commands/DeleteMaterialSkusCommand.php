<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeleteMaterialSkusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'materials:delete-skus {--skus= : Comma-separated list of SKUs to delete}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Permanently delete or archive material / product SKUs and their associated inventory and fixed assets';

    /**
     * Default list of SKUs from delete.doc
     */
    public const DEFAULT_SKUS = [
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

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $rawSkus = $this->option('skus');
        $skus = $rawSkus
            ? array_filter(array_map('trim', explode(',', $rawSkus)))
            : self::DEFAULT_SKUS;

        $this->info("Starting complete permanent deletion for " . count($skus) . " material SKU code(s)...");

        $purgeService = app(\App\Services\ProductPurgeService::class);
        $result = $purgeService->purgeSkus($skus);

        $this->info("Completed material SKU cleanup:");
        $this->line(" - Successfully deleted: {$result['deleted_count']}");
        if (!empty($result['deleted_skus'])) {
            $this->line(" - Purged SKUs: " . implode(', ', $result['deleted_skus']));
        }
        if (!empty($result['failed_skus'])) {
            $this->warn(" - Not found / skipped: " . implode('; ', $result['failed_skus']));
        }

        return Command::SUCCESS;
    }
}
