<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$balances = \App\Models\TallyOpeningClosing::orderBy('date', 'desc')
    ->get()
    ->groupBy('party_name')
    ->map(function($items) {
        return $items->first();
    });
    
$customersList = \App\Models\Customer::where('type', 'web')->get();
$parties = \App\Models\TallyPartySync::get();
$partiesByCode = $parties->keyBy('master_id');
$partiesByName = $parties->keyBy('party_name');

$processedTallyNames = [];
$t1 = 0; $t2 = 0; $t3 = 0;

foreach ($customersList as $customer) {
    $party = $customer->party_code ? $partiesByCode->get($customer->party_code) : null;
    if (!$party) {
        $party = $partiesByName->get($customer->agro_name);
    }
    $tallyName = $party ? $party->party_name : null;
    if ($tallyName) {
        $processedTallyNames[] = $tallyName;
    }
    $b = $tallyName ? $balances->get($tallyName) : null;
    if ($b) {
        $t1 += $b->debit_amt;
        $t2 += $b->credit_amt;
        $t3 += $b->closing_balance_amt;
    }
}

foreach ($parties as $party) {
    $tallyName = $party->party_name;
    if (in_array($tallyName, $processedTallyNames)) {
        continue;
    }
    $b = $balances->get($tallyName);
    if ($b) {
        $t1 += $b->debit_amt;
        $t2 += $b->credit_amt;
        $t3 += $b->closing_balance_amt;
    }
}

var_dump(abs($t1), abs($t2), abs($t3));
