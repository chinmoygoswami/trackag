<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tally_sales_bills')) {
            return;
        }

        Schema::create('tally_sales_bills', function (Blueprint $table) {
            $table->id();
            $table->string('financial_year', 20);
            $table->date('invoice_date');
            $table->string('party_name');
            $table->string('product_name_with_packing');
            $table->string('bill_type', 100);
            $table->decimal('qty', 15, 3);
            $table->decimal('amount', 15, 2);
            $table->decimal('gst_amount', 15, 2);
            $table->decimal('grand_total', 15, 2);
            $table->json('raw_payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_sales_bills');
    }
};
