<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home', ['message' => 'Hello from Inertia!']);
});

Route::fallback(function () {
    return Inertia::render('NotFound');
});
