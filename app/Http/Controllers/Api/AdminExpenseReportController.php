<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\State;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AdminExpenseReportController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'nullable|integer',
            'month' => 'nullable|date_format:Y-m',
            'state_id' => 'nullable|integer',
        ]);

        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized user.',
            ], 401);
        }

        if (! $user->hasAnyRole(['master_admin', 'sub_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only Master Admin and Sub Admin can access this report.',
            ], 403);
        }

        $month = $validated['month'] ?? now()->format('Y-m');
        $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

        $expenses = Expense::query()
            ->with([
                'user:id,name,mobile,state_id',
                'user.state:id,name',
            ])
            ->whereBetween('bill_date', [
                $monthDate->copy()->startOfMonth()->toDateString(),
                $monthDate->copy()->endOfMonth()->toDateString(),
            ])
            ->when($validated['state_id'] ?? null, function (Builder $query, int $stateId) {
                $query->whereHas('user', fn (Builder $userQuery) => $userQuery->where('state_id', $stateId));
            })
            ->when($validated['employee_id'] ?? null, function (Builder $query, int $employeeId) {
                $query->where('user_id', $employeeId);
            })
            ->orderByDesc('bill_date')
            ->orderByDesc('id')
            ->get();

        $items = $expenses->map(fn (Expense $expense) => [
            'id' => $expense->id,
            'employee_id' => $expense->user_id,
            'employee_name' => $expense->user?->name,
            'employee_mobile' => $expense->user?->mobile,
            'state_id' => $expense->user?->state_id,
            'state_name' => $expense->user?->state?->name,
            'bill_date' => $expense->bill_date?->format('Y-m-d'),
            'bill_date_formatted' => $expense->bill_date?->format('jS F Y'),
            'bill_type' => $expense->bill_type,
            'bill_title' => $expense->bill_title,
            'bill_details_description' => $expense->bill_details_description,
            'travel_mode' => $expense->travel_mode,
            'amount' => (float) $expense->amount,
            'image_url' => $expense->image_url,
            'approval_status' => $expense->approval_status ?? 'Pending',
            'reject_reason' => $expense->reject_reason,
            'created_at' => $expense->created_at?->toISOString(),
        ])->values();

        return response()->json([
            'success' => true,
            'data' => [
                'month' => $month,
                'month_name' => $monthDate->format('F Y'),
                'selected_filters' => [
                    'employee_id' => $validated['employee_id'] ?? null,
                    'state_id' => $validated['state_id'] ?? null,
                ],
                'filters' => [
                    'states' => State::query()
                        ->where('status', 1)
                        ->orderBy('name')
                        ->get(['id', 'name']),
                    'employees' => $this->employees($validated['state_id'] ?? null),
                ],
                'summary' => [
                    'total_records' => $items->count(),
                    'total_amount' => round((float) $expenses->sum('amount'), 2),
                ],
                'items' => $items,
            ],
        ]);
    }

    private function employees(?int $stateId)
    {
        return User::query()
            ->with('state:id,name')
            ->where('status', 'Active')
            ->when($stateId, fn (Builder $query, int $stateId) => $query->where('state_id', $stateId))
            ->where(function (Builder $query) {
                $query->whereNull('user_level')
                    ->orWhereNotIn('user_level', ['master_admin', 'sub_admin']);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'state_id']);
    }
}
