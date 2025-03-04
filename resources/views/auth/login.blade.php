@extends('layouts.auth')

@section('title', 'Login')

@section('styles')
    <style>
        .form-input {
            @apply mt-1 block w-full px-3 py-2 bg-white rounded-md shadow-sm border border-gray-300
            focus:outline-none focus:ring-primary-500 focus:border-primary-500 transition duration-150;
        }

        .form-label {
            @apply block text-sm font-medium text-gray-700 mb-1;
        }

        .btn-primary {
            @apply w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm
            text-sm font-medium text-white bg-primary-600 hover:bg-primary-700
            focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition duration-150;
        }

        .error-text {
            @apply mt-1 text-sm text-red-600;
        }
    </style>
@endsection

@section('content')
    <div class="text-center mb-6 opacity-0" id="pageTitle">
        <h2 class="text-2xl font-bold text-gray-800">Welcome Back</h2>
        <p class="mt-2 text-sm text-gray-600">Sign in to your account</p>
    </div>

    @if (session('status'))
        <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6 opacity-0" id="statusAlert" role="alert">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-green-700">{{ session('status') }}</p>
                </div>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="opacity-0" id="loginForm">
        @csrf

        <div class="mb-4">
            <label for="email" class="form-label">Email Address</label>
            <input id="email" name="email" type="email" class="form-input"
                   value="{{ old('email') }}" required autocomplete="email" autofocus>
            @error('email')
            <p class="error-text">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="password" class="form-label">Password</label>
            <input id="password" name="password" type="password" class="form-input"
                   required autocomplete="current-password">
            @error('password')
            <p class="error-text">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center">
                <input id="remember_me" name="remember" type="checkbox"
                       class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                <label for="remember_me" class="ml-2 block text-sm text-gray-700">
                    Remember me
                </label>
            </div>

            @if (Route::has('password.request'))
                <div class="text-sm">
                    <a href="{{ route('password.request') }}" class="text-primary-600 hover:text-primary-500 transition duration-150">
                        Forgot password?
                    </a>
                </div>
            @endif
        </div>

        <div>
            <button type="submit" class="btn-primary" id="submitButton">
                Sign In
            </button>
        </div>
    </form>

    <div class="mt-6 text-center opacity-0" id="registerLink">
        <p class="text-sm text-gray-600">
            Don't have an account?
            <a href="{{ route('register') }}" class="text-primary-600 hover:text-primary-500 font-medium transition duration-150">
                Sign up
            </a>
        </p>
    </div>
@endsection

@section('scripts')
    // Page animation timeline
    const tl = gsap.timeline({ defaults: { ease: "power3.out" } });

    tl.to("#pageTitle", { opacity: 1, y: -10, duration: 0.6, delay: 0.2 })
    .to("#loginForm", { opacity: 1, y: -10, duration: 0.6 }, "-=0.3")
    .to("#registerLink", { opacity: 1, y: -10, duration: 0.6 }, "-=0.3");

    @if(session('status'))
        tl.to("#statusAlert", { opacity: 1, y: -10, duration: 0.6 }, "-=0.4");
    @endif

    // Button animation
    const button = document.getElementById("submitButton");
    button.addEventListener("mouseenter", () => {
    gsap.to(button, { scale: 1.03, duration: 0.3 });
    });

    button.addEventListener("mouseleave", () => {
    gsap.to(button, { scale: 1, duration: 0.3 });
    });

    // Form submission animation
    document.querySelector("form").addEventListener("submit", function(e) {
    const form = this;
    e.preventDefault();

    gsap.to("#loginForm", {
    y: -10,
    opacity: 0.7,
    duration: 0.3,
    onComplete: () => {
    form.submit();
    }
    });
    });
@endsection
