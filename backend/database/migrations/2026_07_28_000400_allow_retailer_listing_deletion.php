<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_changes', function (Blueprint $table): void {
            $table->dropForeign(['retailer_listing_id']);
            $table->foreign('retailer_listing_id')
                ->references('id')
                ->on('retailer_listings')
                ->cascadeOnDelete();
        });

        Schema::table('outbound_clicks', function (Blueprint $table): void {
            $table->dropForeign(['retailer_listing_id']);
            $table->foreign('retailer_listing_id')
                ->references('id')
                ->on('retailer_listings')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('price_changes', function (Blueprint $table): void {
            $table->dropForeign(['retailer_listing_id']);
            $table->foreign('retailer_listing_id')
                ->references('id')
                ->on('retailer_listings')
                ->restrictOnDelete();
        });

        Schema::table('outbound_clicks', function (Blueprint $table): void {
            $table->dropForeign(['retailer_listing_id']);
            $table->foreign('retailer_listing_id')
                ->references('id')
                ->on('retailer_listings')
                ->restrictOnDelete();
        });
    }
};
