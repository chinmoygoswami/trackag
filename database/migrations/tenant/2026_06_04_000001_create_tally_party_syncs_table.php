<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tally_party_syncs')) {
            return;
        }

        Schema::create('tally_party_syncs', function (Blueprint $table) {
            $table->id();
            $table->string('master_id')->nullable();
            $table->string('group_name');
            $table->string('party_name');
            $table->string('phone_1', 30)->nullable();
            $table->string('phone_2', 30)->nullable();
            $table->string('contact_person_name')->nullable();
            $table->string('state')->nullable();
            $table->string('district')->nullable();
            $table->string('gst_no', 50)->nullable();
            $table->date('party_create_date')->nullable();
            $table->text('address')->nullable();
            $table->string('email')->nullable();
            $table->string('pan_no', 50)->nullable();
            $table->integer('credit_days')->nullable();
            $table->decimal('credit_limit', 15, 2)->nullable();
            $table->json('raw_payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_party_syncs');
    }
};
