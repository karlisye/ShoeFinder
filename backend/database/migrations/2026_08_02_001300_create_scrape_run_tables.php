<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scrape_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('retailer_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();
            $table->string('status', 32)->default('queued');
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('successful_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('changed_count')->default(0);
            $table->jsonb('errors')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampTz('applied_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'created_at']);
            $table->index(['retailer_id', 'created_at']);
        });

        Schema::create('scrape_run_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scrape_run_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('retailer_listing_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->unsignedInteger('position');
            $table->string('status', 32)->default('pending');
            $table->text('product_url');
            $table->string('listing_label');
            $table->jsonb('baseline');
            $table->jsonb('result_payload')->nullable();
            $table->jsonb('changes')->nullable();
            $table->jsonb('error')->nullable();
            $table->timestampTz('observed_at')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['scrape_run_id', 'retailer_listing_id'],
                'scrape_run_listing_unique',
            );
            $table->unique(
                ['scrape_run_id', 'position'],
                'scrape_run_position_unique',
            );
            $table->index(['scrape_run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrape_run_items');
        Schema::dropIfExists('scrape_runs');
    }
};
