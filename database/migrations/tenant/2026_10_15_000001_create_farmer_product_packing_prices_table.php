<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('farmer_product_packing_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('packing_id');
            $table->unsignedBigInteger('state_id');
            $table->decimal('cash_price', 10, 2)->nullable();
            $table->decimal('credit_price', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['packing_id', 'state_id']);
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('packing_id')->references('id')->on('product_packings')->onDelete('cascade');
            $table->foreign('state_id')->references('id')->on('states')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('farmer_product_packing_prices');
    }
};
