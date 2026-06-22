<?php

use App\Http\Controllers\RedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/{slug}', RedirectController::class)
    ->where('slug', '[a-zA-Z0-9_-]+');
