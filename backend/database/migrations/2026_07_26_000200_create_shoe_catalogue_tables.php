<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('manufacturer_style_code', 100)->nullable();
            $table->enum('audience', ['men', 'women', 'unisex', 'kids']);
            $table->text('description_lv')->nullable();
            $table->text('description_en')->nullable();
            $table->boolean('active')->default(true);
            $table->timestampsTz();

            $table->unique(
                ['brand_id', 'manufacturer_style_code'],
                'shoe_brand_style_code_unique',
            );
            $table->index(
                ['category_id', 'audience', 'active'],
                'shoe_catalogue_filter_index',
            );
        });

        Schema::create('shoe_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shoe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('colour_id')->constrained()->restrictOnDelete();
            $table->string('manufacturer_variant_code', 100)->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestampsTz();

            $table->unique(
                ['shoe_id', 'colour_id'],
                'shoe_variant_colour_unique',
            );
            $table->unique(
                ['shoe_id', 'manufacturer_variant_code'],
                'shoe_variant_code_unique',
            );
        });

        Schema::create('shoe_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shoe_variant_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->enum('source_type', ['local', 'external']);
            $table->string('path', 2048)->nullable();
            $table->text('external_url')->nullable();
            $table->text('alt_text_lv')->nullable();
            $table->text('alt_text_en')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false)->index();
            $table->timestampsTz();

            $table->unique(
                ['shoe_variant_id', 'sort_order'],
                'shoe_image_variant_order_unique',
            );
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE shoe_images
                ADD CONSTRAINT shoe_images_source_location_check
                CHECK (
                    (
                        source_type = 'local'
                        AND path IS NOT NULL
                        AND path <> ''
                        AND external_url IS NULL
                    )
                    OR
                    (
                        source_type = 'external'
                        AND path IS NULL
                        AND external_url IS NOT NULL
                        AND external_url ~* '^https://'
                    )
                )
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shoe_images');
        Schema::dropIfExists('shoe_variants');
        Schema::dropIfExists('shoes');
    }
};
