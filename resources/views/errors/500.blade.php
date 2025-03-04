@extends('layouts.auth')

@section('title', 'Server Error')

@section('content')
    <div class="text-center mb-6 opacity-0" id="errorTitle">
        <h2 class="text-4xl font-bold text-gray-800">500</h2>
        <h3 class="text-2xl font-semibold text-gray-700 mt-2">Server Error</h3>
        <p class="mt-4 text-gray-600">Oops! Something went wrong on our end.</p>
    </div>

    <div class="flex justify-center mt-8 opacity-0" id="errorImage">
        <svg class="h-40 w-40 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
    </div>

    <div class="mt-8 text-center opacity-0" id="homeLink">
        <a href="{{ url('/') }}" class="inline-flex items-center px-5 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 transition duration-150">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            Back to Home
        </a>
        <p class="text-sm text-gray-500 mt-4">If this problem persists, please contact support.</p>
    </div>
@endsection

@section('scripts')
    // Error page animation
    const tl = gsap.timeline({ defaults: { ease: "power3.out" } });

    tl.to("#errorTitle", { opacity: 1, y: -10, duration: 0.6, delay: 0.2 })
    .to("#errorImage", { opacity: 1, y: -10, duration: 0.6, scale: 1.05 }, "-=0.3")
    .to("#errorImage", { scale: 1, duration: 1, ease: "elastic.out(1, 0.3)" })
    .to("#homeLink", { opacity: 1, y: -10, duration: 0.6 }, "-=0.8");

    // SVG pulsing animation
    gsap.to("#errorImage svg", {
    scale: 1.05,
    duration: 1.5,
    repeat: -1,
    yoyo: true,
    ease: "sine.inOut",
    delay: 1
    });
@endsection
