<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\OrderItem;
use App\Models\State;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BudgetApiController extends Controller
{
    private array $months = [
        'april' => 4,
        'may' => 5,
        'june' => 6,
        'july' => 7,
        'august' => 8,
        'september' => 9,
        'october' => 10,
        'november' => 11,
        'december' => 12,
        'january' => 1,
        'february' => 2,
        'march' => 3,
    ];

    public function annualBudget(Request $request)
    {
        $validated = $request->validate([
            'financial_year' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'state_id' => 'nullable|integer',
            'employee_id' => 'nullable|integer',
            'month' => 'nullable|integer|between:1,12',
        ]);

        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized user.',
            ], 401);
        }

        $isAdmin = $user->hasAnyRole(['master_admin', 'sub_admin']);
        $stateId = $isAdmin ? ($validated['state_id'] ?? null) : $user->state_id;
        $employeeId = $isAdmin ? ($validated['employee_id'] ?? null) : $user->id;
        $financialYear = $validated['financial_year'] ?? $this->currentFinancialYear();
        [$monthName, $monthNumber, $year] = $this->resolveMonth($request, $financialYear);

        $scopeUserIds = $this->scopeUserIds($isAdmin, $user->id, $stateId, $employeeId);
        $budgets = Budget::query()
            ->where('financial_year', $financialYear)
            ->when($stateId, fn (Builder $query, int $stateId) => $query->where('state_id', $stateId))
            ->whereIn('user_id', $scopeUserIds)
            ->get();

        $target = (float) $budgets->sum($monthName);
        $achievement = $this->getAchievement($scopeUserIds, $monthNumber, $year);
        $achievementPercentage = $target > 0 ? round(($achievement / $target) * 100, 2) : null;

        return response()->json([
            'status' => true,
            'data' => [
                'financial_year' => $financialYear,
                'financial_years' => $this->getFinancialYears($scopeUserIds, $stateId, $financialYear),
                'selected_filters' => [
                    'state_id' => $stateId,
                    'employee_id' => $employeeId,
                ],
                'filters' => [
                    'states' => $this->states($isAdmin, $user->state_id),
                    'employees' => $this->employees($isAdmin, $user->id, $stateId),
                ],
                'month' => [
                    'key' => $monthName,
                    'number' => $monthNumber,
                    'year' => $year,
                    'label' => Carbon::createFromDate($year, $monthNumber, 1)->format('M y'),
                ],
                'annual_target' => [
                    'target' => $target,
                    'target_formatted' => $this->formatAmount($target),
                    'achievement' => $achievement,
                    'achievement_formatted' => $this->formatAmount($achievement),
                    'achievement_percentage' => $achievementPercentage,
                    'achievement_percentage_formatted' => $achievementPercentage === null
                        ? '--'
                        : $achievementPercentage . '%',
                ],
            ],
        ]);
    }

    private function currentFinancialYear(): string
    {
        $now = Carbon::now();
        $startYear = $now->month >= 4 ? $now->year : $now->year - 1;

        return $startYear . '-' . substr((string) ($startYear + 1), -2);
    }

    private function resolveMonth(Request $request, string $financialYear): array
    {
        $requestedMonth = (int) $request->input('month', 0);
        $monthNumber = $requestedMonth >= 1 && $requestedMonth <= 12
            ? $requestedMonth
            : $this->defaultMonthForFinancialYear($financialYear);

        $monthName = array_search($monthNumber, $this->months, true) ?: 'april';
        $year = $this->yearForFinancialMonth($financialYear, $monthNumber);

        return [$monthName, $monthNumber, $year];
    }

    private function defaultMonthForFinancialYear(string $financialYear): int
    {
        if ($financialYear === $this->currentFinancialYear()) {
            return Carbon::now()->month;
        }

        return 4;
    }

    private function yearForFinancialMonth(string $financialYear, int $monthNumber): int
    {
        $years = explode('-', $financialYear);
        $startYear = (int) ($years[0] ?? Carbon::now()->year);

        return $monthNumber >= 4 ? $startYear : $startYear + 1;
    }

    private function getAchievement(array $userIds, int $monthNumber, int $year): float
    {
        return (float) OrderItem::whereHas('order', function ($query) use ($userIds, $monthNumber, $year) {
            $query->whereIn('user_id', $userIds)
                ->where('status', 'dispatched')
                ->whereMonth('created_at', $monthNumber)
                ->whereYear('created_at', $year);
        })->sum('grand_total');
    }

    private function getFinancialYears(array $userIds, ?int $stateId, string $selectedFinancialYear): array
    {
        $years = Budget::query()
            ->whereIn('user_id', $userIds)
            ->when($stateId, fn (Builder $query, int $stateId) => $query->where('state_id', $stateId))
            ->distinct()
            ->orderByDesc('financial_year')
            ->pluck('financial_year')
            ->toArray();

        if (!in_array($selectedFinancialYear, $years, true)) {
            $years[] = $selectedFinancialYear;
        }

        rsort($years);

        return array_values($years);
    }

    private function scopeUserIds(bool $isAdmin, int $userId, ?int $stateId, ?int $employeeId): array
    {
        if (! $isAdmin) {
            return [$userId];
        }

        return User::query()
            ->when($stateId, fn (Builder $query, int $stateId) => $query->where('state_id', $stateId))
            ->when($employeeId, fn (Builder $query, int $employeeId) => $query->whereKey($employeeId))
            ->where(function (Builder $query) {
                $query->whereNull('user_level')
                    ->orWhereNotIn('user_level', ['master_admin', 'sub_admin']);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function states(bool $isAdmin, ?int $userStateId)
    {
        return State::query()
            ->where('status', 1)
            ->when(! $isAdmin, fn (Builder $query) => $query->whereKey($userStateId))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function employees(bool $isAdmin, int $userId, ?int $stateId)
    {
        return User::query()
            ->where('status', 'Active')
            ->where('is_active', 1)
            ->when(! $isAdmin, fn (Builder $query) => $query->whereKey($userId))
            ->when($stateId, fn (Builder $query, int $stateId) => $query->where('state_id', $stateId))
            ->where(function (Builder $query) {
                $query->whereNull('user_level')
                    ->orWhereNotIn('user_level', ['master_admin', 'sub_admin']);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'state_id']);
    }

    private function formatAmount(float $amount): string
    {
        return preg_replace('/\.00$/', '', number_format($amount, 2, '.', ','));
    }
}
