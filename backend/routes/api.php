<?php

use App\Http\Controllers\Api\V1\CatalogueController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware('throttle:60,1')
    ->group(function (): void {
        Route::get('/shoes', [CatalogueController::class, 'index'])
            ->name('api.v1.shoes.index');
        Route::get('/shoes/{slug}', [CatalogueController::class, 'show'])
            ->name('api.v1.shoes.show');
        Route::get('/catalog-filters', [CatalogueController::class, 'filters'])
            ->name('api.v1.catalog-filters');
    });
