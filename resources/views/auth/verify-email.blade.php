@extends('layouts.auth')

@section('title', 'Verify Email')

@section('content')
    <div class="text-center mb-6 opacity-0" id="pageTitle">
        <h2 class="text-2xl font-bold text-gray-800">Email Verification</h2>
        <p class="mt-2 text-sm text-gray-600">Please verify your email address</p>
    </div>

    <div class="p-5 bg-white border border-gray-200 rounded-lg shadow-sm opacity-0" id="verifyContent">
        <div class="flex justify-center mb-4">
            <svg class="h-12 w-12 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
        </div>

        <p class="mb-4 text-center text-gray-700">
            Thanks for signing up! Before getting started, could you verify your email address by clicking on the
            link we just emailed to you?
        </p>

        @if (session('status') == 'verification-link-sent')
            <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-4" role="alert">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-green-700">
                            A new verification link has been sent to the email address you provided.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('verification.send') }}" class="mt-4">
            @csrf
            <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition duration-150" id="resendButton">
                Resend Verification Email
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-3">
            @csrf
            <button type="submit" class="w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition duration-150" id="logoutButton">
                Log Out
            </button>
        </form>
    </div>
@endsection

@section('scripts')
    // Page animation timeline
    const tl = gsap.timeline({ defaults: { ease: "power3.out" } });

    tl.to("#pageTitle", { opacity: 1, y: -10, duration: 0.6, delay: 0.2 })
    .to("#verifyContent", { opacity: 1, y: -10, duration: 0.6 }, "-=0.3");

    // Email icon animation
    gsap.to("svg", {
    y: -5,
    duration: 2,
    repeat: -1,
    yoyo: true,
    ease: "sine.inOut",
    delay: 1
    });

    // Button animations
    const resendButton = document.getElementById("resendButton");
    resendButton.addEventListener("mouseenter", () => {
    gsap.to(resendButton, { scale: 1.03, duration: 0.3 });
    });

    resendButton.addEventListener("mouseleave", () => {
    gsap.to(resendButton, { scale: 1, duration: 0.3 });
    });

    const logoutButton = document.getElementById("logoutButton");
    logoutButton.addEventListener("mouseenter", () => {
    gsap.to(logoutButton, { scale: 1.03, duration: 0.3 });
    });

    logoutButton.addEventListener("mouseleave", () => {
    gsap.to(logoutButton, { scale: 1, duration: 0.3 });
    });
@endsection
