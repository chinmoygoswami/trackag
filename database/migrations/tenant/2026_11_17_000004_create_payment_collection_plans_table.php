<?php

use App\Services\PaymentCollectionMonthEnd;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Provision the full plan schema for a fresh install.
        if (! Schema::hasTable('payment_collection_plans')) {
            Schema::create('payment_collection_plans', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id');
                $table->date('collection_month');
                $table->decimal('planned_amount', 15, 2);
                $table->timestamps();
                $table->unique(['customer_id', 'collection_month']);

                $this->addApprovalColumns($table);
                $table->unsignedBigInteger('next_reviewer_id')->nullable()->index();
                $table->decimal('outstanding_at_planning', 15, 2)->nullable();
                $table->decimal('previous_month_end_outstanding', 15, 2)->nullable();
            });

            $this->captureAvailableMonthEnds();

            return;
        }

        // Upgrade databases that ran the original table creation alone.
        if (! Schema::hasColumn('payment_collection_plans', 'approved_at')) {
            Schema::table('payment_collection_plans', function (Blueprint $table) {
                $this->addApprovalColumns($table);
            });
        }

        if (! Schema::hasColumn('payment_collection_plans', 'next_reviewer_id')) {
            Schema::table('payment_collection_plans', function (Blueprint $table) {
                $table->unsignedBigInteger('next_reviewer_id')->nullable()->index();
            });
        }

        foreach (['outstanding_at_planning', 'previous_month_end_outstanding'] as $column) {
            if (! Schema::hasColumn('payment_collection_plans', $column)) {
                Schema::table('payment_collection_plans', fn (Blueprint $table) => $table->decimal($column, 15, 2)->nullable());
            }
        }

        $this->captureAvailableMonthEnds();
    }

    public function down(): void
    {
        $history = config('database.migrations.table', 'migrations');
        $earlier = Schema::hasTable($history)
            ? Schema::getConnection()->table($history)->whereIn('migration', [
                '2026_11_16_000001_create_payment_collection_plans_table',
                '2026_11_17_000001_add_approval_to_payment_collection_plans_table',
                '2026_11_17_000002_add_next_reviewer_to_payment_collection_plans_table',
                '2026_11_17_000003_create_payment_collection_plans_table',
            ])->pluck('migration')
            : collect();

        if ($earlier->isNotEmpty()) {
            Schema::table('payment_collection_plans', function (Blueprint $table) use ($earlier) {
                $table->dropColumn('previous_month_end_outstanding');
                if ($earlier->contains('2026_11_17_000003_create_payment_collection_plans_table')) {
                    return;
                }

                $table->dropColumn('outstanding_at_planning');
                if ($earlier->contains('2026_11_17_000002_add_next_reviewer_to_payment_collection_plans_table')) {
                    return;
                }

                $table->dropIndex(['next_reviewer_id']);
                $table->dropColumn('next_reviewer_id');

                if (! $earlier->contains('2026_11_17_000001_add_approval_to_payment_collection_plans_table')) {
                    $table->dropIndex(['submitted_by']);
                    $table->dropColumn(['pending_amount', 'submitted_by', 'submission_version', 'approved_by', 'approved_at']);
                }
            });

            return;
        }

        Schema::dropIfExists('payment_collection_plans');
    }

    private function captureAvailableMonthEnds(): void
    {
        if (! Schema::hasTable('tally_opening_closings') || ! Schema::hasTable('tally_party_syncs') || ! Schema::hasTable('customers')) {
            return;
        }

        $connection = Schema::getConnection();
        $connection->table('tally_opening_closings')->whereRaw('date = LAST_DAY(date)')
            ->chunkById(250, function ($balances) use ($connection) {
                foreach ($balances as $balance) {
                    PaymentCollectionMonthEnd::capture($connection, $balance->date, $balance->party_name, $balance->closing_balance_amt);
                }
            });
    }

    private function addApprovalColumns(Blueprint $table): void
    {
        $table->decimal('pending_amount', 15, 2)->nullable();
        $table->unsignedBigInteger('submitted_by')->nullable()->index();
        $table->uuid('submission_version')->nullable();
        $table->unsignedBigInteger('approved_by')->nullable();
        $table->timestamp('approved_at')->nullable();
    }
};
