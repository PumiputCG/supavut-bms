<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_budget_mirror', function (Blueprint $table) {
            $table->string('company', 8);
            $table->string('source_id', 24);
            $table->string('fingerprint', 64);
            $table->longText('payload');
            $table->timestamp('imported_at');
            $table->primary(['company', 'source_id']);
        });
        Schema::create('erp_sync_status', function (Blueprint $table) {
            $table->string('dataset', 40)->primary();
            $table->string('revision', 64)->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('changed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('error_type')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_budget_mirror');
        Schema::dropIfExists('erp_sync_status');
    }
};
