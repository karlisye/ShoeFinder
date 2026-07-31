<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

Route::get('/up', function () {
    $database = 'connected';
    $redis = 'connected';

    try {
        DB::select('select 1');
    } catch (Throwable) {
        $database = 'unavailable';
    }

    try {
        Redis::connection()->ping();
    } catch (Throwable) {
        $redis = 'unavailable';
    }

    return response()->json([
        'status' => $database === 'connected' && $redis === 'connected'
            ? 'ok'
            : 'error',
        'application' => 'up',
        'database' => $database,
        'redis' => $redis,
    ], $database === 'connected' && $redis === 'connected' ? 200 : 503);
})->name('health');
