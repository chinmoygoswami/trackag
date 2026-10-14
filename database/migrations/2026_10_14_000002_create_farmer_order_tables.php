<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farmer_order_sequences', function (Blueprint $table) {
            $table->string('state_code', 16)->primary();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('farmer_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no')->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('farmer_id');
            $table->enum('order_type', ['cash', 'debit']);
            $table->unsignedBigInteger('depo_id')->nullable();
            $table->string('delivery_place')->nullable();
            $table->string('preferred_transport')->nullable();
            $table->text('remark')->nullable();
            $table->text('remark2')->nullable();
            $table->string('lr_number')->nullable();
            $table->string('transport_name')->nullable();
            $table->string('destination')->nullable();
            $table->dateTime('dispatch_date')->nullable();
            $table->string('dispatch_image')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'hold', 'part_dispatched', 'dispatched'])->default('pending');
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('farmer_id')->references('id')->on('farmers')->onDelete('cascade');
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('farmer_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('farmer_order_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('packing_id')->nullable();
            $table->integer('shipper_size')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->decimal('gst', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2);
            $table->integer('qty')->default(1);
            $table->timestamps();
            $table->foreign('farmer_order_id')->references('id')->on('farmer_orders')->onDelete('cascade');
            $table->index(['product_id', 'packing_id']);
        });

        Schema::create('farmer_order_dispatches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('farmer_order_id');
            $table->unsignedBigInteger('farmer_order_item_id');
            $table->integer('dispatch_qty');
            $table->enum('dispatch_type', ['partial', 'final'])->nullable();
            $table->timestamps();
            $table->foreign('farmer_order_id')->references('id')->on('farmer_orders')->onDelete('cascade');
            $table->foreign('farmer_order_item_id')->references('id')->on('farmer_order_items')->onDelete('cascade');
        });

        Schema::create('farmer_order_dispatch_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('farmer_order_dispatch_id');
            $table->string('lr_number')->nullable();
            $table->string('transport_name')->nullable();
            $table->string('vehicle_no')->nullable();
            $table->string('dispatch_image')->nullable();
            $table->dateTime('dispatch_date')->nullable();
            $table->timestamps();
            $table->foreign('farmer_order_dispatch_id')->references('id')->on('farmer_order_dispatches')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farmer_order_dispatch_details');
        Schema::dropIfExists('farmer_order_dispatches');
        Schema::dropIfExists('farmer_order_items');
        Schema::dropIfExists('farmer_orders');
        Schema::dropIfExists('farmer_order_sequences');
    }
};
