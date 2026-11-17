<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

class PaymentCollectionMonthEnd
{
    public static function capture(ConnectionInterface $connection, string $date, string $partyName, string $amount): void
    {
        $month = CarbonImmutable::parse($date)->addDay()->toDateString();
        $ledgers = $connection->table('tally_party_syncs')->where('party_name', $partyName);
        $customers = $connection->table('customers')->where('type', 'web')->where('is_active', 1)
            ->where(function (Builder $parties) use ($ledgers, $partyName) {
                $parties->whereIn('party_code', (clone $ledgers)->select('master_id'))
                    ->orWhere(function (Builder $fallback) use ($ledgers, $partyName) {
                        $fallback->where('agro_name', $partyName)->whereExists((clone $ledgers)->selectRaw('1'))
                            ->whereNotExists(fn (Builder $matched) => $matched->selectRaw('1')->from('tally_party_syncs')
                                ->whereColumn('tally_party_syncs.master_id', 'customers.party_code'));
                    });
            })->pluck('id');

        if ($customers->isEmpty()) {
            return;
        }

        $timestamp = now();
        $connection->table('payment_collection_plans')->upsert($customers->map(fn ($id) => [
            'customer_id' => $id,
            'collection_month' => $month,
            'planned_amount' => '0.00',
            'previous_month_end_outstanding' => $amount,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->all(), ['customer_id', 'collection_month'], ['previous_month_end_outstanding']);
    }
}
