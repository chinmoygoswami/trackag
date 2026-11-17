<?php

namespace App\Observers;

use App\Models\TallyOpeningClosing;
use App\Services\PaymentCollectionMonthEnd;
use Throwable;

class PaymentCollectionMonthEndObserver
{
    public function saved(TallyOpeningClosing $balance): void
    {
        if (! $balance->date || ! $balance->date->isLastOfMonth()) {
            return;
        }

        try {
            $connection = $balance->getConnection();
            if ($connection->getSchemaBuilder()->hasColumn('payment_collection_plans', 'previous_month_end_outstanding')) {
                PaymentCollectionMonthEnd::capture($connection, $balance->date->toDateString(), $balance->party_name, $balance->closing_balance_amt);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
