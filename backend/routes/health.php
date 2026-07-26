<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/up', function () {
    try {
        DB::select('select 1');
    } catch (Throwable) {
        return response()->json([
            'status' => 'error',
            'application' => 'up',
            'database' => 'unavailable',
        ], 503);
    }

    return response()->json([
        'status' => 'ok',
        'application' => 'up',
        'database' => 'connected',
    ]);
})->name('health');
