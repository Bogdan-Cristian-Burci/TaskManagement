<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;

class OAuthSocialController extends Controller
{
    /**
     * Supported OAuth providers
     */
    protected array $supportedProviders = ['github', 'google', 'facebook', 'twitter'];

    /**
     * Default scopes for each provider
     */
    protected array $providerScopes = [
        'github' => ['user:email'],
        'google' => ['email', 'profile'],
        'facebook' => ['email'],
        'twitter' => ['email']
    ];

    /**
     * Handle OAuth callback from provider.
     *
     * @param string $provider
     * @return JsonResponse
     */
    public function handleProviderCallback(string $provider): JsonResponse
    {
        if (!in_array($provider, $this->supportedProviders)) {
            return response()->json(['error' => 'Unsupported provider'], 400);
        }

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();

            // Find user by provider and provider ID first
            $user = User::where('provider', $provider)
                ->where('provider_id', $socialUser->getId())
                ->first();

            // If not found by provider ID, try to find by email
            if (!$user && $socialUser->getEmail()) {
                $user = User::where('email', $socialUser->getEmail())->first();

                // If user exists but doesn't have provider info, update it
                if ($user) {
                    $user->update([
                        'provider' => $provider,
                        'provider_id' => $socialUser->getId()
                    ]);
                }
            }

            // Create new user if not found
            if (!$user) {
                $user = User::create([
                    'name' => $socialUser->getName() ?: $socialUser->getNickname(),
                    'email' => $socialUser->getEmail(),
                    'password' => bcrypt(Str::random(32)),
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'avatar' => $socialUser->getAvatar(),
                    'email_verified_at' => now(), // Social login provides verified email
                ]);

                // Assign default role
                $user->assignRole('user');
            }

            // Update avatar if it's not set yet
            if (!$user->avatar && $socialUser->getAvatar()) {
                $user->update(['avatar' => $socialUser->getAvatar()]);
            }

            // Create token with appropriate scopes
            $token = $user->createToken('Social Auth Token', ['read-user'])->accessToken;

            // Load relationships
            $user->load(['roles', 'permissions', 'organisation']);

            return response()->json([
                'token' => $token,
                'user' => new UserResource($user),
                'message' => 'Authentication successful'
            ]);

        } catch (\Exception $e) {
            Log::error('Social authentication error: ' . $e->getMessage(), [
                'provider' => $provider,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Authentication failed',
                'message' => config('app.debug') ? $e->getMessage() : 'An error occurred during authentication'
            ], 401);
        }
    }

    /**
     * Redirect to provider for authentication.
     *
     * @param string $provider
     * @return RedirectResponse|JsonResponse
     */
    public function redirectToProvider(string $provider): RedirectResponse|JsonResponse
    {
        if (!in_array($provider, $this->supportedProviders)) {
            return response()->json(['error' => 'Unsupported provider'], 400);
        }

        try {
            $scopes = $this->providerScopes[$provider] ?? [];

            return Socialite::driver($provider)
                ->scopes($scopes)
                ->stateless()
                ->redirect();
        } catch (\Exception $e) {
            Log::error('Social redirect error: ' . $e->getMessage(), [
                'provider' => $provider,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Redirect failed',
                'message' => config('app.debug') ? $e->getMessage() : 'An error occurred during redirection'
            ], 500);
        }
    }

    /**
     * Get list of supported OAuth providers.
     *
     * @return JsonResponse
     */
    public function providers(): JsonResponse
    {
        $providers = array_map(function($provider) {
            return [
                'name' => $provider,
                'url' => route('oauth.redirect', ['provider' => $provider])
            ];
        }, $this->supportedProviders);

        return response()->json(['providers' => $providers]);
    }
}
