<?php

namespace App\Services;

class MonthlyProductReport
{
    public const ORDER_STATUSES = ['approved', 'part_dispatched', 'dispatched'];

    public static function formatNumber(float|int $value, int $decimals = 2): string
    {
        $parts = explode('.', number_format($value, $decimals, '.', ''));
        $parts[0] = preg_replace('/\B(?=(\d{2})*\d{3}$)/', ',', $parts[0]);

        return implode('.', $parts);
    }

    public static function orderRows(iterable $orders, iterable $states = []): array
    {
        $stateNames = self::stateNames($states);
        $rows = [];
        foreach ($orders as $order) {
            $state = $order->user?->state;
            $stateKey = self::stateKey($state?->name ?: 'Unassigned State');
            $stateName = $stateNames[$stateKey] ?? ($state?->name ?: 'Unassigned State');
            foreach ($order->items as $item) {
                $packing = trim(($item->packing?->packing_value ?? '').' '.($item->packing?->packing_size ?? ''));
                $name = $item->product?->product_name ?: 'Product #'.$item->product_id;
                $rows[] = [
                    'month' => $order->created_at->format('Y-m'),
                    'party_key' => 'customer:'.($order->party_id ?? 0),
                    'party_name' => $order->customer?->agro_name ?: $order->customer?->name ?: 'Unassigned Party',
                    'employee_key' => $order->user_id ?? 0,
                    'employee_name' => $order->user?->name ?: 'Unassigned Employee',
                    'state_key' => $stateKey,
                    'state_name' => $stateName, 'state_id' => $state?->id,
                    'product_key' => 'product:'.$item->product_id.':packing:'.($item->packing_id ?? 0),
                    'product_name' => $name.($packing !== '' ? ' - '.$packing : ''),
                    'product_category_id' => $item->product?->product_category_id,
                    'qty' => (float) $item->qty, 'value' => (float) $item->total_price,
                ];
            }
        }

        return $rows;
    }

    public static function normalize(?string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name ?? '')));
    }

    // State-only matching: party and product names retain their existing rules.
    private static function stateKey(string $name): string
    {
        return preg_replace('/\s+/u', '', self::normalize($name));
    }

    private static function stateNames(iterable $states): array
    {
        $names = [];
        foreach ($states as $state) {
            $names[self::stateKey($state->name)] = $state->name;
        }

        return $names;
    }

    /** Resolve Tally names within this request's tenant; never retain mappings across requests. */
    public static function salesRows(iterable $sales, iterable $customers, iterable $syncs, iterable $products, bool $isAdmin, array $allowedIds, iterable $states = []): array
    {
        $stateNames = self::stateNames($states);
        $syncByCode = [];
        $syncByName = [];
        foreach ($syncs as $sync) {
            $syncByCode[(string) $sync->master_id] = $sync;
            $syncByName[self::normalize($sync->party_name)] = $sync;
        }
        $aliases = [];
        foreach ($customers as $customer) {
            foreach ([$customer->state, $customer->user?->state] as $assignedState) {
                if ($assignedState) {
                    $stateNames[self::stateKey($assignedState->name)] ??= $assignedState->name;
                }
            }
            $sync = $syncByCode[(string) $customer->party_code] ?? null;
            foreach ([$customer->party_code, $customer->agro_name, $customer->name, $sync?->party_name] as $alias) {
                $key = self::normalize($alias);
                if ($key !== '') {
                    $aliases[$key][$customer->id] = $customer;
                }
            }
        }
        $productList = iterator_to_array((function () use ($products) {
            yield from $products;
        })());
        usort($productList, fn ($a, $b) => strlen($b->product_name) <=> strlen($a->product_name));
        $rows = [];
        foreach ($sales as $sale) {
            $partyKey = self::normalize($sale->party_name);
            $matches = $aliases[$partyKey] ?? [];
            // Ambiguous names must not grant access to another employee's party.
            $customer = count($matches) === 1 ? reset($matches) : null;
            if (! $isAdmin && (! $customer || ! in_array($customer->user_id, $allowedIds))) {
                continue;
            }
            $productName = trim($sale->product_name_with_packing);
            $productKey = self::normalize($productName);
            $categoryId = null;
            foreach ($productList as $product) {
                $base = self::normalize($product->product_name);
                if ($base !== '' && ($productKey === $base || str_starts_with($productKey, $base.' -') || str_starts_with($productKey, $base.' ('))) {
                    $categoryId = $product->product_category_id;
                    break;
                }
            }
            $state = $customer?->state ?: $customer?->user?->state;
            $stateName = $state?->name ?: ($syncByName[$partyKey]->state ?? null) ?: 'Unassigned State';
            $stateKey = self::stateKey($stateName);
            $stateName = $stateNames[$stateKey] ?? $stateName;
            $rows[] = [
                'month' => $sale->invoice_date->format('Y-m'),
                'party_key' => $customer ? 'customer:'.$customer->id : 'tally:'.$partyKey,
                'party_name' => $customer?->agro_name ?: trim($sale->party_name),
                'employee_key' => $customer?->user_id ?? 0,
                'employee_name' => $customer?->user?->name ?: 'Unassigned Employee',
                'state_key' => $stateKey, 'state_name' => $stateName,
                'state_id' => $state?->id, 'product_key' => $productKey,
                'product_name' => $productName, 'product_category_id' => $categoryId,
                'qty' => (float) $sale->qty, 'value' => (float) $sale->amount,
            ];
        }

        return $rows;
    }

    public static function build(iterable $rows, string $dimension, array $months, array $filters = []): array
    {
        $empty = ['monthly' => array_fill_keys($months, ['qty' => 0.0, 'value' => 0.0]), 'qty' => 0.0, 'value' => 0.0];
        $groups = [];
        $total = $empty;
        $productIds = [];
        $lineCount = 0;
        foreach ($rows as $sale) {
            $month = $sale['month'];
            if (! isset($total['monthly'][$month])) {
                continue;
            }
            foreach ($filters as $field => $value) {
                if ($value !== null && $value !== '' && (string) $sale[$field] !== (string) $value) {
                    continue 2;
                }
            }
            $id = $sale[$dimension.'_key'];
            $productId = $sale['product_key'];
            $groups[$id] ??= $empty + ['name' => $sale[$dimension.'_name'], 'products' => []];
            $groups[$id]['products'][$productId] ??= $empty + ['name' => $sale['product_name']];
            $productIds[$productId] = true;
            foreach ([&$groups[$id], &$groups[$id]['products'][$productId], &$total] as &$row) {
                $row['monthly'][$month]['qty'] += $sale['qty'];
                $row['monthly'][$month]['value'] += $sale['value'];
                $row['qty'] += $sale['qty'];
                $row['value'] += $sale['value'];
            }
            unset($row);
            $lineCount++;
        }
        uasort($groups, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));
        foreach ($groups as &$group) {
            uasort($group['products'], fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));
        }
        unset($group);

        return ['groups' => $groups, 'total' => $total, 'product_count' => count($productIds), 'line_count' => $lineCount];
    }
}
