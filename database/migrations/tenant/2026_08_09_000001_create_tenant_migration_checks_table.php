<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_migration_checks')) {
            return;
        }

        Schema::create('tenant_migration_checks', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('migration_successful');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_migration_checks');
    }
};
