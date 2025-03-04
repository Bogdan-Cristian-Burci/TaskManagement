@extends('layouts.auth')

@section('title', 'Register')

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
        <h2 class="text-2xl font-bold text-gray-800">Create Account</h2>
        <p class="mt-2 text-sm text-gray-600">Sign up to get started</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="opacity-0" id="registerForm">
        @csrf

        <div class="mb-4">
            <label for="name" class="form-label">Name</label>
            <input id="name" name="name" type="text" class="form-input"
                   value="{{ old('name') }}" required autocomplete="name" autofocus>
            @error('name')
            <p class="error-text">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="email" class="form-label">Email Address</label>
            <input id="email" name="email" type="email" class="form-input"
                   value="{{ old('email') }}" required autocomplete="email">
            @error('email')
            <p class="error-text">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="password" class="form-label">Password</label>
            <input id="password" name="password" type="password" class="form-input"
                   required autocomplete="new-password">
            @error('password')
            <p class="error-text">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-6">
            <label for="password-confirm" class="form-label">Confirm Password</label>
            <input id="password-confirm" name="password_confirmation" type="password"
                   class="form-input" required autocomplete="new-password">
        </div>

        <div>
            <button type="submit" class="btn-primary" id="submitButton">
                Register
            </button>
        </div>
    </form>

    <div class="mt-6 text-center opacity-0" id="loginLink">
        <p class="text-sm text-gray-600">
            Already have an account?
            <a href="{{ route('login') }}" class="text-primary-600 hover:text-primary-500 font-medium transition duration-150">
                Sign in
            </a>
        </p>
    </div>
@endsection

@section('scripts')
    // Page animation timeline
    const tl = gsap.timeline({ defaults: { ease: "power3.out" } });

    tl.to("#pageTitle", { opacity: 1, y: -10, duration: 0.6, delay: 0.2 })
    .to("#registerForm", { opacity: 1, y: -10, duration: 0.6 }, "-=0.3")
    .to("#loginLink", { opacity: 1, y: -10, duration: 0.6 }, "-=0.3");

    // Form input animations
    const inputs = document.querySelectorAll('.form-input');
    inputs.forEach(input => {
    input.addEventListener('focus', () => {
    gsap.to(input, {
    borderColor: '#4f46e5',
    boxShadow: '0 0 0 3px rgba(99, 102, 241, 0.2)',
    duration: 0.3
    });
    });

    input.addEventListener('blur', () => {
    if (!input.value) {
    gsap.to(input, {
    borderColor: '#d1d5db',
    boxShadow: 'none',
    duration: 0.3
    });
    }
    });
    });

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

    gsap.to("#registerForm", {
    y: -10,
    opacity: 0.7,
    duration: 0.3,
    onComplete: () => {
    form.submit();
    }
    });
    });
@endsection
