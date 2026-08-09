<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tally_partywise_payment_credits')) {
            return;
        }

        Schema::create('tally_partywise_payment_credits', function (Blueprint $table) {
            $table->id();
            $table->string('sr_no')->nullable();
            $table->string('party_name');
            $table->date('payment_date');
            $table->string('payment_mode')->nullable();
            $table->decimal('credit_amount', 15, 2);
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_partywise_payment_credits');
    }
};
