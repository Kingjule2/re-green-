<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Browser routes
|--------------------------------------------------------------------------
|
| The React app is a single-page application: Laravel serves one Blade view and
| the client decides what to render from the path, so every browser route
| resolves to the same shell. Anything under /api is handled by routes/api.php
| and never reaches this fallback.
|
*/
Route::view('/{path?}', 'welcome')
    ->where('path', '.*')
    ->name('spa');
