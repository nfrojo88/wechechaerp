<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MaterialToConsumableService;

class ConvertMaterialsToConsumableCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'materials:convert-to-consumable {--skus= : Comma-separated list of SKUs to convert}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert material / product items from Fixed Asset to Consumable, removing asset unit codes while preserving store inventory on-hand balances';

    /**
     * Default list of SKUs from error.doc
     */
    public const DEFAULT_SKUS = [
        'FA-BUST',
        'FA-BALD',
        'FA-B RUS',
        'FA-CAB2',
        'FA-CONT',
        'FA-BOP',
        'FA-COVE',
        'FA-CUPH',
        'FA-CUP2',
        'FA-DATA',
        'FA-DISH',
        'FA-DOU3',
        'FA-ELBO',
        'FA-ELE5',
        'FA-ELE9',
        'MAT-0577',
        'FA-FACI',
        'FA-FEMA',
        'FA-FLOW',
        'FA-GALL',
        'FE-GEN2',
        'FA-GUTT',
        'FA-HDP4',
        'FA-HDP5',
        'FA-HDP6',
        'FA-HDP7',
        'FA-HDP8',
        'FA-HDP9',
        'FA-HDP10',
        'FA-HDP11',
        'FA-HDP12',
        'FA-HDP13',
        'FA-HDP14',
        'FA-HDP15',
        'FA-HDP16',
        'FA-HDP17',
        'FA-HOTM',
        'FA-HWBU',
        'FA-KACH',
        'FA-LIDF',
        'FA-MAG2',
        'FA-PHOT',
        'FA-POWE',
        'FA-PPR10',
        'FA-PRE2',
        'FA-QACC',
        'FA-SIN3',
        'FA-SIN2',
        'FA-SINK',
        'FA-TVCA',
        'FA-TV50',
        'FA-VGAC',
        'FA-WCWI',
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

        $this->info("Starting conversion to Consumable for " . count($skus) . " material item(s)...");

        $service = app(MaterialToConsumableService::class);
        $result = $service->convertSkusToConsumable($skus);

        $this->info("Conversion completed successfully:");
        $this->line(" - Products converted to Consumable: " . $result['converted_count']);
        $this->line(" - Fixed Asset parent records removed: " . $result['fixed_assets_removed']);
        $this->line(" - Serialized Unit codes removed: " . $result['units_removed']);

        if (!empty($result['converted_products'])) {
            $this->line("\nConverted Materials:");
            foreach ($result['converted_products'] as $item) {
                $this->line("   [#{$item['id']}] {$item['sku']} - {$item['name']}");
            }
        }

        if (!empty($result['not_found'])) {
            $this->warn("\nNot found in database (skipped): " . implode(', ', $result['not_found']));
        }

        return Command::SUCCESS;
    }
}
