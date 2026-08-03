<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scrape_runs', function (Blueprint $table): void {
            $table->string('cancellation_reason', 32)->nullable();
            $table->timestampTz('cancelled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('scrape_runs', function (Blueprint $table): void {
            $table->dropColumn(['cancellation_reason', 'cancelled_at']);
        });
    }
};
