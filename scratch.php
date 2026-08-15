<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $item = [
        "financial_year" => "2023-24",
        "invoice_date" => "2023-10-15",
        "invoice_no" => "INV-1001",
        "party_name" => "John Doe Enterprises",
        "product_name_with_packing" => "Widget A (10kg)",
        "bill_type" => "Tax Invoice",
        "qty" => 10.500,
        "amount" => 1050.00,
        "gst_amount" => 189.00,
        "grand_total" => 1239.00,
        "voucher_type" => "Sales"
    ];
    $item['raw_payload'] = $item;
    
    // We mock the TallyController logic here
    $item['gst_amount'] = $item['gst_amount'] ?? 0;
    \App\Models\TallySalesBill::create($item);
    echo "Created SalesBill successfully\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
