<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->index(['approval_status', 'trip_date'], 'trips_expense_report_index');
        });
        Schema::table('expenses', function (Blueprint $table) {
            $table->index(['user_id', 'bill_date', 'approval_status'], 'expenses_report_user_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('expenses_report_user_date_index');
        });
        Schema::table('trips', function (Blueprint $table) {
            $table->dropIndex('trips_expense_report_index');
        });
    }
};
