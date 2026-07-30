<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('retailer_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('format', 16);
            $table->string('status', 32)->default('uploaded');
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('ready_count')->default(0);
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedInteger('invalid_count')->default(0);
            $table->json('errors')->nullable();
            $table->timestampTz('applied_at')->nullable();
            $table->timestampsTz();

            $table->index(['retailer_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('feed_import_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('feed_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('source_record')->nullable();
            $table->string('identity');
            $table->string('outcome', 32);
            $table->string('reason');
            $table->json('normalized_payload')->nullable();
            $table->json('raw_payload')->nullable();
            $table->json('issues')->nullable();
            $table->foreignId('matched_listing_id')
                ->nullable()
                ->constrained('retailer_listings')
                ->nullOnDelete();
            $table->foreignId('matched_variant_id')
                ->nullable()
                ->constrained('shoe_variants')
                ->nullOnDelete();
            $table->foreignId('selected_variant_id')
                ->nullable()
                ->constrained('shoe_variants')
                ->nullOnDelete();
            $table->string('resolution', 32)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();

            $table->index(['feed_import_id', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_import_items');
        Schema::dropIfExists('feed_imports');
    }
};
