<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tally_opening_closings')) {
            return;
        }

        Schema::create('tally_opening_closings', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('party_name');
            $table->decimal('opening_balance_amt', 15, 2);
            $table->decimal('credit_amt', 15, 2);
            $table->decimal('debit_amt', 15, 2);
            $table->decimal('closing_balance_amt', 15, 2);
            $table->json('raw_payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_opening_closings');
    }
};
