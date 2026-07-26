<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('website_url')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestampsTz();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name_lv');
            $table->string('name_en');
            $table->text('description_lv')->nullable();
            $table->text('description_en')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->boolean('active')->default(true)->index();
            $table->timestampsTz();
        });

        Schema::create('colours', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name_lv');
            $table->string('name_en');
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->boolean('active')->default(true)->index();
            $table->timestampsTz();
        });

        Schema::create('sizes', function (Blueprint $table): void {
            $table->id();
            $table->decimal('eu_size', 4, 1)->unique();
            $table->string('label', 8)->unique();
            $table->unsignedSmallInteger('sort_order')->unique();
            $table->boolean('active')->default(true)->index();
            $table->timestampsTz();
        });

        Schema::create('retailers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('website_url')->nullable();
            $table->string('logo_path', 2048)->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retailers');
        Schema::dropIfExists('sizes');
        Schema::dropIfExists('colours');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('brands');
    }
};
