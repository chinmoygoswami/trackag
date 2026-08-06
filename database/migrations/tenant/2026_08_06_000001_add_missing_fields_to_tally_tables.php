<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            $tenant = tenancy()->tenant;
            if ($tenant && !empty($tenant->tenancy_db_name)) {
                config(['database.connections.tenant.database' => $tenant->tenancy_db_name]);
                \Illuminate\Support\Facades\DB::purge('tenant');
                \Illuminate\Support\Facades\DB::reconnect('tenant');
            }
        }

        Schema::connection('tenant')->table('tally_party_syncs', function (Blueprint $table) {
            $table->text('address')->nullable()->after('party_create_date');
            $table->string('email')->nullable()->after('address');
            $table->string('pan_no', 50)->nullable()->after('email');
            $table->integer('credit_days')->nullable()->after('pan_no');
            $table->decimal('credit_limit', 15, 2)->nullable()->after('credit_days');
        });

        Schema::connection('tenant')->table('tally_sales_bills', function (Blueprint $table) {
            $table->string('invoice_no')->nullable()->after('invoice_date');
            $table->string('voucher_type', 100)->nullable()->after('grand_total');
        });

        Schema::connection('tenant')->table('tally_partywise_payment_credits', function (Blueprint $table) {
            $table->string('voucher_no')->nullable()->after('credit_amount');
            $table->string('voucher_type')->nullable()->after('voucher_no');
        });
    }

    public function down(): void
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            $tenant = tenancy()->tenant;
            if ($tenant && !empty($tenant->tenancy_db_name)) {
                config(['database.connections.tenant.database' => $tenant->tenancy_db_name]);
                \Illuminate\Support\Facades\DB::purge('tenant');
                \Illuminate\Support\Facades\DB::reconnect('tenant');
            }
        }

        Schema::connection('tenant')->table('tally_party_syncs', function (Blueprint $table) {
            $table->dropColumn(['address', 'email', 'pan_no', 'credit_days', 'credit_limit']);
        });

        Schema::connection('tenant')->table('tally_sales_bills', function (Blueprint $table) {
            $table->dropColumn(['invoice_no', 'voucher_type']);
        });

        Schema::connection('tenant')->table('tally_partywise_payment_credits', function (Blueprint $table) {
            $table->dropColumn(['voucher_no', 'voucher_type']);
        });
    }
};
