<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/', function () {
    return view('index');
});

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

// Handle the password reset form submission
Route::post('/reset-password', function (Request $request) {
    // This route will be handled by your API, not needed here
    return redirect('/')->with('success', 'Password has been reset successfully');
})->name('password.update');
