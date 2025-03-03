@extends('layout')

@section('content')
    <div class="min-h-screen flex items-center justify-center bg-colab-background px-4 py-12 sm:px-6 lg:px-8">
        <div class="w-full max-w-md">
            <div class="bg-white shadow-lg rounded-xl overflow-hidden transform transition-all reset-card opacity-0 translate-y-12">
                <!-- Header with decoration -->
                <div class="bg-colab-gradient p-6 relative overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-r from-colab-secondary/80 to-colab-primary/80"></div>
                    <div class="relative z-10">
                        <h2 class="text-center text-3xl font-extrabold text-white">
                            Reset Password
                        </h2>
                        <p class="mt-2 text-center text-sm text-white/90">
                            Create a new secure password for your account
                        </p>
                    </div>

                    <!-- Decorative elements -->
                    <div class="absolute top-0 right-0 -mt-6 -mr-6">
                        <div class="text-colab-accent/30">
                            <svg class="h-24 w-24" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z" />
                                <path d="M9.879 16.121A3 3 0 1012.015 11.1c.248.365.481.76.684 1.175.247.494.47 1.025.659 1.573.057.147.139.29.202.431.055.123.1.248.151.373.145.364.264.749.388 1.128.072.216.173.424.244.641.211.642.359 1.305.429 1.968.079.601.09 1.217.075 1.82a5 5 0 01-3.431-3.119 5 5 0 01-.537-1.977 4.938 4.938 0 01.1-1.031c.024-.115.051-.235.08-.355z" />
                            </svg>
                        </div>
                    </div>
                    <div class="absolute bottom-0 left-0 -mb-6 -ml-6">
                        <div class="text-colab-accent/20">
                            <svg class="h-20 w-20" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Form section -->
                <div class="bg-white px-6 py-8">
                    <div id="error-message" class="hidden bg-red-50 border-l-4 border-red-400 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700" id="error-text"></p>
                            </div>
                        </div>
                    </div>

                    <div id="success-message" class="hidden bg-green-50 border-l-4 border-green-400 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-green-700" id="success-text"></p>
                            </div>
                        </div>
                    </div>

                    <form id="reset-form" class="space-y-6">
                        <input type="hidden" id="token" value="{{ $token }}">
                        <input type="hidden" id="email" value="{{ $email }}">

                        <div class="form-group relative">
                            <label for="email" class="block text-sm font-medium text-colab-text mb-1">Email</label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                        <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                                    </svg>
                                </div>
                                <input type="text" id="email-display" class="focus:ring-colab-secondary focus:border-colab-secondary block w-full pl-10 pr-12 py-3 border-gray-300 rounded-md bg-gray-100 text-gray-500" value="{{ $email }}" disabled>
                            </div>
                        </div>

                        <div class="form-group password-field opacity-0">
                            <label for="password" class="block text-sm font-medium text-colab-text mb-1">New Password</label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <input type="password" id="password" name="password" class="focus:ring-colab-secondary focus:border-colab-secondary block w-full pl-10 pr-12 py-3 border-gray-300 rounded-md" required>
                                <div class="absolute inset-y-0 right-0 flex items-center">
                                    <button type="button" id="toggle-password" class="h-full px-3 text-gray-400 focus:outline-none">
                                        <svg class="h-5 w-5 eye-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                            <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                        </svg>
                                        <svg class="h-5 w-5 eye-closed hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd" />
                                            <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="mt-1">
                                <div class="text-xs text-gray-500 flex items-center">
                                    <span id="password-strength-text">Password strength: </span>
                                    <div class="ml-2 flex-1 h-1 bg-gray-200 rounded-full overflow-hidden">
                                        <div id="password-strength-meter" class="h-full bg-gray-400" style="width: 0%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group password-field opacity-0">
                            <label for="password_confirmation" class="block text-sm font-medium text-colab-text mb-1">Confirm Password</label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <input type="password" id="password_confirmation" name="password_confirmation" class="focus:ring-colab-secondary focus:border-colab-secondary block w-full pl-10 pr-3 py-3 border-gray-300 rounded-md" required>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" id="submit-btn" class="group relative w-full flex justify-center py-3 px-4 border border-transparent rounded-md text-white bg-colab-secondary hover:bg-colab-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-colab-secondary transition-all duration-300 transform opacity-0 scale-95">
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <svg class="h-5 w-5 text-white group-hover:text-colab-accent transition-colors duration-300" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                                Reset Password
                            </button>
                        </div>
                    </form>
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-center">
                    <a href="/" class="text-sm text-colab-secondary hover:text-colab-primary transition-colors duration-300">
                        Return to login page
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script type="module">
        // Import GSAP
        import { gsap } from '/node_modules/gsap/index.js';

        document.addEventListener('DOMContentLoaded', function() {
            // Animate card appearance with GSAP
            const tl = gsap.timeline();

            tl.to(".reset-card", {
                duration: 0.8,
                opacity: 1,
                y: 0,
                ease: "power3.out"
            });

            tl.to(".password-field", {
                duration: 0.5,
                opacity: 1,
                stagger: 0.2,
                ease: "power2.out"
            }, "-=0.3");

            tl.to("#submit-btn", {
                duration: 0.5,
                opacity: 1,
                scale: 1,
                ease: "back.out(1.4)"
            }, "-=0.2");

            // Toggle password visibility
            const togglePassword = document.getElementById('toggle-password');
            const passwordInput = document.getElementById('password');

            togglePassword.addEventListener('click', function() {
                const eyeOpen = togglePassword.querySelector('.eye-open');
                const eyeClosed = togglePassword.querySelector('.eye-closed');

                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    eyeOpen.classList.add('hidden');
                    eyeClosed.classList.remove('hidden');
                } else {
                    passwordInput.type = 'password';
                    eyeOpen.classList.remove('hidden');
                    eyeClosed.classList.add('hidden');
                }
            });

            // Password strength meter
            const passwordStrengthMeter = document.getElementById('password-strength-meter');
            const passwordStrengthText = document.getElementById('password-strength-text');

            passwordInput.addEventListener('input', function() {
                const value = passwordInput.value;
                const strength = calculatePasswordStrength(value);

                passwordStrengthMeter.style.width = strength.percent + '%';

                if (strength.percent <= 25) {
                    passwordStrengthMeter.classList.remove('bg-yellow-500', 'bg-green-500');
                    passwordStrengthMeter.classList.add('bg-red-500');
                    passwordStrengthText.textContent = 'Password strength: Weak';
                } else if (strength.percent <= 50) {
                    passwordStrengthMeter.classList.remove('bg-red-500', 'bg-green-500');
                    passwordStrengthMeter.classList.add('bg-yellow-500');
                    passwordStrengthText.textContent = 'Password strength: Medium';
                } else {
                    passwordStrengthMeter.classList.remove('bg-red-500', 'bg-yellow-500');
                    passwordStrengthMeter.classList.add('bg-green-500');
                    passwordStrengthText.textContent = 'Password strength: Strong';
                }
            });

            // Calculate password strength
            function calculatePasswordStrength(password) {
                let strength = 0;

                // If password is empty, return 0
                if (password.length === 0) {
                    return { score: 0, percent: 0 };
                }

                // Add length score
                strength += Math.min(password.length * 4, 25);

                // Add character variety score
                if (/[a-z]/.test(password)) strength += 10;
                if (/[A-Z]/.test(password)) strength += 15;
                if (/\d/.test(password)) strength += 15;
                if (/[^a-zA-Z0-9]/.test(password)) strength += 20;

                // Add complexity score
                const variations = [
                    /\d/.test(password),
                    /[a-z]/.test(password),
                    /[A-Z]/.test(password),
                    /[^a-zA-Z0-9]/.test(password)
                ].filter(Boolean).length;

                strength += variations * 10;

                return {
                    score: Math.min(strength, 100),
                    percent: Math.min(strength, 100)
                };
            }

            // Form submission
            const form = document.getElementById('reset-form');
            const errorMessage = document.getElementById('error-message');
            const errorText = document.getElementById('error-text');
            const successMessage = document.getElementById('success-message');
            const successText = document.getElementById('success-text');
            const confirmPasswordInput = document.getElementById('password_confirmation');
            const token = document.getElementById('token').value;
            const email = document.getElementById('email').value;

            form.addEventListener('submit', function(e) {
                e.preventDefault();

                // Clear previous messages
                errorMessage.classList.add('hidden');
                successMessage.classList.add('hidden');

                // Simple validation
                const password = passwordInput.value;
                const passwordConfirmation = confirmPasswordInput.value;

                if (password.length < 8) {
                    showError('Password must be at least 8 characters long');
                    shakeElement(passwordInput);
                    return;
                }

                if (password !== passwordConfirmation) {
                    showError('Passwords do not match');
                    shakeElement(confirmPasswordInput);
                    return;
                }

                // Disable button and show loading state
                const submitBtn = document.getElementById('submit-btn');
                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Resetting...';

                // Send API request
                fetch('/api/reset-password', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        token,
                        email,
                        password,
                        password_confirmation: passwordConfirmation
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        // Reset button state
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;

                        if (data.message === 'Password reset successful') {
                            showSuccess('Your password has been reset successfully!');

                            // Create success animation with GSAP
                            gsap.to('.reset-card', {
                                duration: 0.5,
                                backgroundColor: '#F0FDF4',
                                borderColor: '#86EFAC',
                                boxShadow: '0 0 0 2px rgba(134, 239, 172, 0.4)',
                                ease: 'power2.out'
                            });

                            // Redirect after success
                            setTimeout(() => {
                                window.location.href = '/';
                            }, 3000);
                        } else {
                            showError(data.message || 'An error occurred while resetting your password');
                        }
                    })
                    .catch(error => {
                        // Reset button state
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;

                        showError('An error occurred while processing your request');
                        console.error(error);
                    });
            });

            function showError(message) {
                errorText.textContent = message;
                errorMessage.classList.remove('hidden');

                // Animate error message
                gsap.fromTo(errorMessage,
                    { y: -20, opacity: 0 },
                    { duration: 0.4, y: 0, opacity: 1, ease: 'power2.out' }
                );
            }

            function showSuccess(message) {
                successText.textContent = message;
                successMessage.classList.remove('hidden');

                // Animate success message
                gsap.fromTo(successMessage,
                    { y: -20, opacity: 0 },
                    { duration: 0.4, y: 0, opacity: 1, ease: 'power2.out' }
                );
            }

            function shakeElement(element) {
                gsap.to(element, {
                    x: 10,
                    duration: 0.1,
                    repeat: 3,
                    yoyo: true,
                    ease: "power2.inOut",
                    onComplete: () => {
                        gsap.to(element, {
                            x: 0,
                            duration: 0.1
                        });
                    }
                });
            }
        });
    </script>
@endsection
