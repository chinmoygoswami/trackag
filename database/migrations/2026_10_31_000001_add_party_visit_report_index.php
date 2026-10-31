<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('party_visits', function (Blueprint $table) {
            $table->index(['customer_id', 'visited_date'], 'party_visits_customer_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('party_visits', function (Blueprint $table) {
            $table->dropIndex('party_visits_customer_date_index');
        });
    }
};
