<?php

use App\Http\Controllers\OutboundRedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/go/{listing}', OutboundRedirectController::class)
    ->middleware('throttle:120,1')
    ->name('outbound.redirect');

Route::get('/', function () {
    return view('welcome');
});
