<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateReceiptItemsTableAndUpdateReceipts extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // 1. Enhance receipts table with indexed FS No and OCR metadata
        if (Schema::hasTable('receipts')) {
            Schema::table('receipts', function (Blueprint $table) {
                if (!Schema::hasColumn('receipts', 'fs_no')) {
                    $table->string('fs_no')->nullable()->index()->after('vendor_tin');
                }
                if (!Schema::hasColumn('receipts', 'mrc_no')) {
                    $table->string('mrc_no')->nullable()->after('fs_no');
                }
                if (!Schema::hasColumn('receipts', 'buyer_tin')) {
                    $table->string('buyer_tin')->nullable()->after('vendor_tin');
                }
                if (!Schema::hasColumn('receipts', 'ocr_engine')) {
                    $table->string('ocr_engine', 50)->default('gemini')->after('ocr_raw_text');
                }
                if (!Schema::hasColumn('receipts', 'confidence')) {
                    $table->string('confidence', 50)->default('high')->after('ocr_engine');
                }
                if (!Schema::hasColumn('receipts', 'needs_review')) {
                    $table->boolean('needs_review')->default(false)->after('confidence');
                }
            });
        }

        // 2. Create receipt_items table (one row per item)
        if (!Schema::hasTable('receipt_items')) {
            Schema::create('receipt_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('receipt_id')->constrained('receipts')->cascadeOnDelete();
                $table->text('item_description')->nullable();
                $table->string('vat_category', 10)->default('G'); // Col A (G/S)
                $table->string('calendar_type', 10)->default('G'); // Col B (G/E)
                $table->integer('purchase_type')->default(3); // Col C (3: Line 100)
                $table->string('uom', 50)->default('9'); // Col J (9 OTHER, 7 PCS, 2 KG, etc.)
                $table->decimal('qty', 15, 2)->default(1.00); // Col K
                $table->decimal('unit_price', 15, 2)->default(0.00); // Col L
                $table->decimal('total_value', 15, 2)->default(0.00); // Col M
                $table->decimal('vat_amount', 15, 2)->default(0.00); // Col N
                $table->decimal('value_after_vat', 15, 2)->default(0.00); // Col O
                $table->boolean('is_flagged')->default(false);
                $table->json('flag_reasons')->nullable();
                $table->timestamps();
            });
        }

        // 3. Backfill existing receipts into receipt_items and sync FS No
        if (Schema::hasTable('receipts') && Schema::hasTable('receipt_items')) {
            $existingReceipts = DB::table('receipts')->get();
            foreach ($existingReceipts as $r) {
                $parsed = [];
                if (!empty($r->parsed_data)) {
                    $parsed = is_string($r->parsed_data) ? (json_decode($r->parsed_data, true) ?: []) : (array)$r->parsed_data;
                }

                $fsNo = $r->fs_no ?? ($parsed['fs_no'] ?? null);
                $mrcNo = $r->mrc_no ?? ($parsed['machine_no'] ?? null);
                $buyerTin = $r->buyer_tin ?? ($parsed['buyer_tin'] ?? null);

                // Update receipt fields if missing
                DB::table('receipts')->where('id', $r->id)->update([
                    'fs_no'     => $fsNo,
                    'mrc_no'    => $mrcNo,
                    'buyer_tin' => $buyerTin,
                ]);

                // Check if this receipt already has items
                $hasItems = DB::table('receipt_items')->where('receipt_id', $r->id)->exists();
                if (!$hasItems) {
                    $items = $parsed['items'] ?? ($parsed['line_items'] ?? []);
                    if (!empty($items) && is_array($items)) {
                        foreach ($items as $item) {
                            $desc = $item['name'] ?? ($item['item_description'] ?? ($item['description'] ?? 'Material'));
                            $qty = (float)($item['qty'] ?? 1);
                            $unitPrice = (float)($item['unit_price'] ?? 0);
                            $totalVal = (float)($item['total'] ?? ($item['total_value'] ?? ($qty * $unitPrice)));
                            $vat = (float)($item['vat'] ?? ($item['vat_amount'] ?? round($totalVal * 0.15, 2)));
                            $afterVat = (float)($item['value_after_vat'] ?? round($totalVal + $vat, 2));

                            DB::table('receipt_items')->insert([
                                'receipt_id'       => $r->id,
                                'item_description' => $desc,
                                'vat_category'     => $parsed['vat_category'] ?? 'G',
                                'calendar_type'    => $parsed['calendar_type'] ?? 'G',
                                'purchase_type'    => (int)($parsed['purchase_type'] ?? 3),
                                'uom'              => (string)($item['uom'] ?? ($parsed['uom_id'] ?? '9')),
                                'qty'              => $qty,
                                'unit_price'       => $unitPrice,
                                'total_value'      => $totalVal,
                                'vat_amount'       => $vat,
                                'value_after_vat'  => $afterVat,
                                'is_flagged'       => false,
                                'created_at'       => $r->created_at ?? now(),
                                'updated_at'       => $r->updated_at ?? now(),
                            ]);
                        }
                    } else {
                        // Fallback single line item for existing receipt
                        DB::table('receipt_items')->insert([
                            'receipt_id'       => $r->id,
                            'item_description' => $r->description ?: 'Purchased Material',
                            'vat_category'     => $parsed['vat_category'] ?? 'G',
                            'calendar_type'    => $parsed['calendar_type'] ?? 'G',
                            'purchase_type'    => (int)($parsed['purchase_type'] ?? 3),
                            'uom'              => (string)($parsed['uom_id'] ?? '9'),
                            'qty'              => 1.00,
                            'unit_price'       => (float)$r->subtotal,
                            'total_value'      => (float)$r->subtotal,
                            'vat_amount'       => (float)$r->vat_amount,
                            'value_after_vat'  => (float)$r->total_amount,
                            'is_flagged'       => false,
                            'created_at'       => $r->created_at ?? now(),
                            'updated_at'       => $r->updated_at ?? now(),
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('receipt_items');
    }
}
