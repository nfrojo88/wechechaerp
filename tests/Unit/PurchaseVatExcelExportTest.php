<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\CreditStorePayment;
use App\Models\CreditStoreLedger;
use App\Http\Controllers\Finance\FinanceTaxReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\Xlsx\Reader as XlsxReader;

class PurchaseVatExcelExportTest extends TestCase
{
    protected User $user;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            DB::statement('PRAGMA foreign_keys = OFF;');
        } catch (\Throwable $e) {}

        $this->setupSchema();

        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->user = User::create([
            'name'     => 'Tax Admin',
            'email'    => 'tax_admin_' . uniqid() . '@wechecha.com',
            'password' => bcrypt('password'),
        ]);
        $this->user->assignRole($role);
        Auth::login($this->user);

        $this->project = Project::create([
            'name'       => 'Main Project',
            'code'       => 'MP-01',
            'created_by' => $this->user->id,
        ]);
    }

    protected function setupSchema(): void
    {
        Schema::dropIfExists('receipt_items');
        Schema::dropIfExists('credit_store_payments');
        Schema::dropIfExists('credit_store_ledgers');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('expense_requests');
        Schema::dropIfExists('system_settings');

        Schema::create('system_settings', function ($table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->string('group')->default('general');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('receipts', function ($table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->foreignId('uploaded_by')->nullable();
            $table->foreignId('project_id')->nullable();
            $table->string('receipt_number')->nullable();
            $table->string('fs_no')->nullable();
            $table->string('fs_no_raw')->nullable();
            $table->string('mrc_no')->nullable();
            $table->string('vendor_name')->nullable();
            $table->string('vendor_tin')->nullable();
            $table->string('buyer_tin')->nullable();
            $table->date('receipt_date')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('file_path')->nullable();
            $table->string('file_url')->nullable();
            $table->string('file_type')->default('image');
            $table->string('category')->nullable();
            $table->string('description')->nullable();
            $table->string('status')->default('approved');
            $table->boolean('tin_valid')->default(true);
            $table->boolean('fs_no_valid')->default(true);
            $table->boolean('needs_review')->default(false);
            $table->string('purchasable_type')->nullable();
            $table->unsignedBigInteger('purchasable_id')->nullable();
            $table->json('parsed_data')->nullable();
            $table->timestamps();
        });

        Schema::create('receipt_items', function ($table) {
            $table->id();
            $table->foreignId('receipt_id');
            $table->string('item_description');
            $table->string('vat_category')->default('G');
            $table->string('calendar_type')->default('G');
            $table->integer('purchase_type')->default(3);
            $table->string('uom')->default('9');
            $table->decimal('qty', 12, 4)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('total_value', 15, 2)->default(0);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->decimal('value_after_vat', 15, 2)->default(0);
            $table->boolean('is_flagged')->default(false);
            $table->timestamps();
        });

        Schema::create('credit_store_ledgers', function ($table) {
            $table->id();
            $table->foreignId('project_id')->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('item_description')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('credit_store_payments', function ($table) {
            $table->id();
            $table->foreignId('credit_store_ledger_id')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('gross_amount', 15, 2)->nullable();
            $table->decimal('vat_amount', 15, 2)->nullable();
            $table->decimal('vat_rate', 5, 2)->default(15);
            $table->string('vat_type')->default('inclusive');
            $table->string('reference_no')->nullable();
            $table->string('receipt_path')->nullable();
            $table->date('payment_date')->nullable();
            $table->timestamps();
        });
    }

    public function test_export_purchase_vat_excel_headers_and_data_conform_to_pro_doc()
    {
        // 1. Create an itemized Receipt with ReceiptItem
        $r1 = Receipt::create([
            'vendor_name'    => 'BERTA SUPER MARKET',
            'vendor_tin'     => '006870105', // Has leading zeros to test stripping
            'receipt_number' => 'FS00111683',
            'fs_no'          => 'FS00111683',
            'mrc_no'         => 'FGI0026630',
            'receipt_date'   => '2026-04-09',
            'subtotal'       => 52.17,
            'vat_amount'     => 7.83,
            'total_amount'   => 60.00,
            'project_id'     => $this->project->id,
        ]);

        ReceiptItem::create([
            'receipt_id'       => $r1->id,
            'item_description' => 'LION TEA',
            'vat_category'     => 'G',
            'calendar_type'    => 'G',
            'purchase_type'    => 3,
            'uom'              => '9',
            'qty'              => 1.0,
            'unit_price'       => 52.17,
            'total_value'      => 52.17,
            'vat_amount'       => 7.83,
            'value_after_vat'  => 60.00,
        ]);

        // 2. Create a standalone Receipt without items (should be virtualized into export)
        $r2 = Receipt::create([
            'vendor_name'    => 'INFINITY GENERAL TRADING',
            'vendor_tin'     => '101558374',
            'receipt_number' => 'M0000346',
            'fs_no'          => 'M0000346',
            'mrc_no'         => null,
            'receipt_date'   => '2026-04-11',
            'subtotal'       => 37391.30,
            'vat_amount'     => 5608.70,
            'total_amount'   => 43000.00,
            'description'    => 'SANITARY',
            'project_id'     => $this->project->id,
        ]);

        // 3. Create a CreditStorePayment slip
        $ledger = CreditStoreLedger::create([
            'project_id'       => $this->project->id,
            'supplier_name'    => 'SUPER DOUBLE T GENERAL TRADING',
            'notes'            => 'TANKER',
        ]);

        CreditStorePayment::create([
            'credit_store_ledger_id' => $ledger->id,
            'amount'                 => 244465.00,
            'gross_amount'           => 212578.26,
            'vat_amount'             => 31886.74,
            'vat_rate'               => 15.00,
            'vat_type'               => 'inclusive',
            'reference_no'           => 'FS00009082',
            'receipt_path'           => 'receipts/test_slip.jpg',
            'payment_date'           => '2026-04-14',
        ]);

        // Trigger export via controller
        $controller = app(FinanceTaxReportController::class);
        $request = new Request([
            'type'            => 'purchase_vat',
            'from_date'       => '2026-04-01',
            'to_date'         => '2026-04-30',
            'ethiopian_year'  => 2018,
            'ethiopian_month' => 8,
        ]);

        $response = $controller->exportExcel($request);

        $this->assertEquals(200, $response->getStatusCode());

        $rows = [];
        if ($response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
            $filePath = $response->getFile()->getPathname();
            $this->assertFileExists($filePath);

            $reader = new XlsxReader();
            $reader->open($filePath);
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = array_map(fn($c) => $c->getValue(), $row->getCells());
                }
            }
            $reader->close();
        } else {
            // Streamed CSV Fallback
            ob_start();
            $response->sendContent();
            $content = ob_get_clean();

            // Strip UTF-8 BOM if present
            $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
            $lines = explode("\n", trim($content));
            foreach ($lines as $line) {
                if (trim($line) === '') continue;
                $rows[] = str_getcsv($line);
            }
        }

        // 1. Verify exact 15-column headers from pro.doc
        $expectedHeaders = [
            'VAT CATEGORY  (G=GOODS;S=SERVICES)',
            'CALENDAR TYPE (E=ETHIOPIAN;G=GREGORIAN)',
            'Types of purchase. 1 = Taxable-local Purchase of Capital Assets (Line No. 65) 2 = Taxable-imported Purchase of Capital Assets (Line No. 75) 3 = Taxable-local Purchase of Inputs (Line No. 100) 4 = Taxable-imported Purchase of Inputs (Line No. 110) 5 = Taxable-general Expense Inputs Purchase (Line No. 120) 6= Tax Exempted-purchase with no vat or uncollectible inputs (Line no. 85 or Line no. 130)    (Please type 1 or 2 or 3 or 4 or 5 or 6).This field is mandatory.',
            'TIN..This field  is not mandatory.',
            'Seller name (if Seller has no TIN or item is not locally purchased) This field is not mandatory.',
            'Date of purchase/Customs Declaration No.  Dispatched Date (Please use  dd/mm/yyyy date format).  This field is mandatory.',
            'MRC Number..This field is not mandatory.',
            'Vat receipt number/ Customs Declaration Number.This field is mandatory.',
            'Description.This field is mandatory.',
            'Unit of Measure (type ID 2-10). 2 KG 3 ML 4 GM 5 LIT 6 MT 7 PCS 8 CT 9 OTHER 10 PC This field is mandatory.',
            'Quantity. Enter number.Don\'t use comma (,) or Quatation ("") This field is  mandatory.',
            'Unit Price. Enter number only .Don\'t use comma (,) or Quatation ("") This field is  mandatory.',
            'Total value',
            'vat',
            'value after vat',
        ];

        $this->assertCount(4, $rows); // Header + 3 data rows
        $this->assertEquals($expectedHeaders, $rows[0]);

        // 2. Verify row 1: itemized receipt
        $row1 = $rows[1];
        $this->assertEquals('G', $row1[0]); // VAT Category
        $this->assertEquals('G', $row1[1]); // Calendar Type
        $this->assertEquals(3, $row1[2]);   // Type of purchase
        $this->assertEquals(6870105, $row1[3]); // TIN stripped of leading zeros
        $this->assertEquals('BERTA SUPER MARKET', $row1[4]);
        $this->assertEquals('09/04/2026', $row1[5]); // dd/mm/yyyy
        $this->assertEquals('FGI0026630', $row1[6]); // MRC
        $this->assertEquals('FS00111683', $row1[7]); // FS
        $this->assertEquals('LION TEA', $row1[8]);
        $this->assertEquals(9, $row1[9]);   // UOM ID
        $this->assertEquals(1, $row1[10]);  // Qty
        $this->assertEquals(52.17, $row1[11]); // Unit Price
        $this->assertEquals(52.17, $row1[12]); // Total Value
        $this->assertEquals(7.83, $row1[13]);  // VAT
        $this->assertEquals(60.0, $row1[14]);  // Value after VAT

        // 3. Verify row 2: standalone receipt without items
        $row2 = $rows[2];
        $this->assertEquals('G', $row2[0]);
        $this->assertEquals('G', $row2[1]);
        $this->assertEquals(3, $row2[2]);
        $this->assertEquals(101558374, $row2[3]);
        $this->assertEquals('INFINITY GENERAL TRADING', $row2[4]);
        $this->assertEquals('11/04/2026', $row2[5]);
        $this->assertEquals('', $row2[6]); // Empty MRC
        $this->assertEquals('M0000346', $row2[7]);
        $this->assertEquals('SANITARY', $row2[8]);
        $this->assertEquals(9, $row2[9]);
        $this->assertEquals(1, $row2[10]);
        $this->assertEquals(37391.3, $row2[11]);
        $this->assertEquals(37391.3, $row2[12]);
        $this->assertEquals(5608.7, $row2[13]);
        $this->assertEquals(43000.0, $row2[14]);

        // 4. Verify row 3: Credit store payment slip
        $row3 = $rows[3];
        $this->assertEquals('G', $row3[0]);
        $this->assertEquals('G', $row3[1]);
        $this->assertEquals(3, $row3[2]);
        $this->assertEquals('SUPER DOUBLE T GENERAL TRADING', $row3[4]);
        $this->assertEquals('14/04/2026', $row3[5]);
        $this->assertEquals('FS00009082', $row3[7]);
        $this->assertEquals('TANKER', $row3[8]);
        $this->assertEquals(212578.26, $row3[12]);
        $this->assertEquals(31886.74, $row3[13]);
        $this->assertEquals(244465.0, $row3[14]);
    }

    public function test_export_purchase_vat_excel_search_filter()
    {
        $r1 = Receipt::create([
            'vendor_name'    => 'CORCHA MESK TRADING',
            'vendor_tin'     => '77551000',
            'receipt_number' => 'FS00001289',
            'fs_no'          => 'FS00001289',
            'mrc_no'         => 'HEJ0028891',
            'receipt_date'   => '2026-04-14',
            'subtotal'       => 85739.13,
            'vat_amount'     => 12860.87,
            'total_amount'   => 98600.00,
            'project_id'     => $this->project->id,
        ]);

        ReceiptItem::create([
            'receipt_id'       => $r1->id,
            'item_description' => 'GLUE',
            'vat_category'     => 'G',
            'calendar_type'    => 'G',
            'purchase_type'    => 3,
            'uom'              => '7',
            'qty'              => 1.0,
            'unit_price'       => 85739.13,
            'total_value'      => 85739.13,
            'vat_amount'       => 12860.87,
            'value_after_vat'  => 98600.00,
        ]);

        $controller = app(FinanceTaxReportController::class);
        $request = new Request([
            'type'            => 'purchase_vat',
            'from_date'       => '2026-04-01',
            'to_date'         => '2026-04-30',
            'search'          => 'GLUE',
        ]);

        $response = $controller->exportExcel($request);
        $this->assertEquals(200, $response->getStatusCode());

        $rows = [];
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = explode("\n", trim($content));
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $rows[] = str_getcsv($line);
        }

        $this->assertCount(2, $rows); // Header + 1 match
        $this->assertEquals('GLUE', $rows[1][8]);
        $this->assertEquals(77551000, $rows[1][3]);
        $this->assertEquals('HEJ0028891', $rows[1][6]);
        $this->assertEquals('FS00001289', $rows[1][7]);
        $this->assertEquals(85739.13, $rows[1][12]);
        $this->assertEquals(12860.87, $rows[1][13]);
        $this->assertEquals(98600.0, $rows[1][14]);
    }
}
