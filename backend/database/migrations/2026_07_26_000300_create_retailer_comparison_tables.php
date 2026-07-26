<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retailer_listings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shoe_variant_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('retailer_id')
                ->constrained()
                ->restrictOnDelete();
            $table->text('product_url');
            $table->text('affiliate_url')->nullable();
            $table->string('retailer_external_id', 191)->nullable();
            $table->string('retailer_sku', 191)->nullable();
            $table->string('gtin', 14)->nullable();
            $table->string('manufacturer_style_code', 100)->nullable();
            $table->text('raw_title')->nullable();
            $table->string('raw_colour')->nullable();
            $table->enum('source_type', ['manual', 'feed', 'api'])
                ->default('manual');
            $table->jsonb('raw_payload')->nullable();
            $table->decimal('current_price', 12, 2);
            $table->decimal('original_price', 12, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('delivery_cost', 12, 2)->nullable();
            $table->unsignedSmallInteger('delivery_min_days')->nullable();
            $table->unsignedSmallInteger('delivery_max_days')->nullable();
            $table->text('delivery_note_lv')->nullable();
            $table->text('delivery_note_en')->nullable();
            $table->boolean('active')->default(true);
            $table->timestampTz('last_checked_at')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['shoe_variant_id', 'retailer_id'],
                'retailer_listing_variant_retailer_unique',
            );
            $table->unique(
                ['retailer_id', 'retailer_external_id'],
                'retailer_listing_external_id_unique',
            );
            $table->unique(
                ['retailer_id', 'retailer_sku'],
                'retailer_listing_sku_unique',
            );
            $table->index('gtin');
            $table->index('manufacturer_style_code');
            $table->index('source_type');
            $table->index(
                ['active', 'last_checked_at'],
                'retailer_listing_freshness_index',
            );
            $table->index('current_price');
        });

        Schema::create('retailer_listing_sizes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('retailer_listing_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('size_id')->constrained()->restrictOnDelete();
            $table->boolean('in_stock')->default(false);
            $table->decimal('price', 12, 2)->nullable();
            $table->timestampsTz();

            $table->unique(
                ['retailer_listing_id', 'size_id'],
                'retailer_listing_size_unique',
            );
            $table->index(
                ['size_id', 'in_stock'],
                'retailer_listing_size_stock_index',
            );
        });

        Schema::create('price_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('retailer_listing_id')
                ->constrained()
                ->restrictOnDelete();
            $table->decimal('price', 12, 2);
            $table->decimal('original_price', 12, 2)->nullable();
            $table->timestampTz('observed_at')->useCurrent();

            $table->index(
                ['retailer_listing_id', 'observed_at'],
                'price_change_listing_observed_index',
            );
        });

        Schema::create('outbound_clicks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('retailer_listing_id')
                ->constrained()
                ->restrictOnDelete();
            $table->enum('locale', ['lv', 'en'])->default('lv');
            $table->text('referrer_path')->nullable();
            $table->timestampTz('clicked_at')->useCurrent();

            $table->index(
                ['retailer_listing_id', 'clicked_at'],
                'outbound_click_listing_clicked_index',
            );
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE retailer_listings
                ADD CONSTRAINT retailer_listings_product_url_check
                    CHECK (product_url ~* '^https?://'),
                ADD CONSTRAINT retailer_listings_affiliate_url_check
                    CHECK (affiliate_url IS NULL OR affiliate_url ~* '^https?://'),
                ADD CONSTRAINT retailer_listings_current_price_check
                    CHECK (current_price >= 0),
                ADD CONSTRAINT retailer_listings_original_price_check
                    CHECK (original_price IS NULL OR original_price >= current_price),
                ADD CONSTRAINT retailer_listings_currency_check
                    CHECK (currency ~ '^[A-Z]{3}$'),
                ADD CONSTRAINT retailer_listings_delivery_cost_check
                    CHECK (delivery_cost IS NULL OR delivery_cost >= 0),
                ADD CONSTRAINT retailer_listings_delivery_days_check
                    CHECK (
                        (delivery_min_days IS NULL OR delivery_min_days >= 0)
                        AND (delivery_max_days IS NULL OR delivery_max_days >= 0)
                        AND (
                            delivery_min_days IS NULL
                            OR delivery_max_days IS NULL
                            OR delivery_min_days <= delivery_max_days
                        )
                    ),
                ADD CONSTRAINT retailer_listings_gtin_check
                    CHECK (
                        gtin IS NULL
                        OR gtin ~ '^([0-9]{8}|[0-9]{12}|[0-9]{13}|[0-9]{14})$'
                    )
                SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE retailer_listing_sizes
                ADD CONSTRAINT retailer_listing_sizes_price_check
                CHECK (price IS NULL OR price >= 0)
                SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE price_changes
                ADD CONSTRAINT price_changes_price_check
                    CHECK (price >= 0),
                ADD CONSTRAINT price_changes_original_price_check
                    CHECK (original_price IS NULL OR original_price >= price)
                SQL);

            DB::statement(<<<'SQL'
                ALTER TABLE outbound_clicks
                ADD CONSTRAINT outbound_clicks_referrer_path_check
                CHECK (
                    referrer_path IS NULL
                    OR (
                        referrer_path LIKE '/%'
                        AND referrer_path NOT LIKE '//%'
                    )
                )
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_clicks');
        Schema::dropIfExists('price_changes');
        Schema::dropIfExists('retailer_listing_sizes');
        Schema::dropIfExists('retailer_listings');
    }
};
