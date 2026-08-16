<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Placeholder until auth scaffolding provides it. EnforceSessionAbsoluteTimeout
// redirects here once a session outlives config('session.absolute_lifetime').
Route::get('/login', fn () => response('Login'))->name('login');
