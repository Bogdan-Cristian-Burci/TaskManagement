<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/', function () {
    return view('index');
});
// Authentication Routes
Route::get('/login', function () {
    return view('auth.login');
})->name('login');

Route::get('/register', function () {
    return view('auth.register');
})->name('register');

Route::get('/forgot-password', function () {
    return view('auth.forgot-password');
})->name('password.request');

// Password reset routes
Route::get('/reset-password', function (Request $request) {
    // Validate token and email are present
    if (!$request->has('token') || !$request->has('email')) {
        return redirect('/')->with('error', 'Invalid password reset link');
    }

    return view('auth.reset-password', [
        'token' => $request->token,
        'email' => $request->email
    ]);
})->name('password.reset');

Route::get('/verify-email', function () {
    return view('auth.verify-email');
})->name('verification.notice');

//// Two-factor Authentication routes for web (these serve the views)
//Route::get('/two-factor/setup', function () {
//    return view('auth.two-factor-setup');
//})->name('two-factor.setup');
//
//Route::get('/two-factor/challenge', function () {
//    return view('auth.two-factor-challenge');
//})->name('two-factor.challenge');
