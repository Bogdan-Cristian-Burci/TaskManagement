<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorAuthController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Generate 2FA secret and QR code.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if 2FA is already enabled
        if ($user->two_factor_secret) {
            return response()->json([
                'message' => 'Two-factor authentication is already enabled'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Generate a new secret
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        // Store the secret temporarily
        $user->two_factor_temp_secret = $secret;
        $user->save();

        // Generate the QR code URL
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        return response()->json([
            'message' => 'Two-factor authentication secret generated',
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl
        ], Response::HTTP_OK);
    }

    /**
     * Verify and activate 2FA.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
            'password' => 'required|string'
        ]);

        $user = $request->user();

        // Verify password first
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Password is incorrect'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Check if there's a temporary secret
        if (!$user->two_factor_temp_secret) {
            return response()->json([
                'message' => 'No two-factor authentication secret found'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Verify the code
        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($user->two_factor_temp_secret, $request->code);

        if (!$valid) {
            return response()->json([
                'message' => 'Invalid verification code'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Activate 2FA
        $user->two_factor_secret = $user->two_factor_temp_secret;
        $user->two_factor_temp_secret = null;
        $user->two_factor_recovery_codes = json_encode($this->generateRecoveryCodes());
        $user->save();

        return response()->json([
            'message' => 'Two-factor authentication enabled successfully',
            'recovery_codes' => json_decode($user->two_factor_recovery_codes)
        ], Response::HTTP_OK);
    }

    /**
     * Disable 2FA.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function disable(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string'
        ]);

        $user = $request->user();

        // Verify password first
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Password is incorrect'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Check if 2FA is enabled
        if (!$user->two_factor_secret) {
            return response()->json([
                'message' => 'Two-factor authentication is not enabled'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Disable 2FA
        $user->two_factor_secret = null;
        $user->two_factor_temp_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->save();

        return response()->json([
            'message' => 'Two-factor authentication disabled successfully'
        ], Response::HTTP_OK);
    }

    /**
     * Generate recovery codes.
     *
     * @return array
     */
    private function generateRecoveryCodes(): array
    {
        $recoveryCodes = [];

        for ($i = 0; $i < 8; $i++) {
            $recoveryCodes[] = substr(str_replace(['-', '_'], '', Hash::make(uniqid())), 0, 10);
        }

        return $recoveryCodes;
    }

    /**
     * Authenticate with 2FA code.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function authenticate(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        // Check if 2FA is enabled
        if (!$user->two_factor_secret) {
            return response()->json([
                'message' => 'Two-factor authentication is not enabled'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Verify the code
        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($user->two_factor_secret, $request->code);

        if (!$valid) {
            return response()->json([
                'message' => 'Invalid authentication code'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Mark session as 2FA authenticated
        $request->session()->put('two_factor_authenticated', true);

        return response()->json([
            'message' => 'Two-factor authentication successful'
        ], Response::HTTP_OK);
    }

    /**
     * Use recovery code for 2FA.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function recovery(Request $request): JsonResponse
    {
        $request->validate([
            'recovery_code' => 'required|string',
        ]);

        $user = $request->user();

        // Check if 2FA is enabled
        if (!$user->two_factor_secret) {
            return response()->json([
                'message' => 'Two-factor authentication is not enabled'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate recovery code
        $valid = $user->validateRecoveryCode($request->recovery_code);

        if (!$valid) {
            return response()->json([
                'message' => 'Invalid recovery code'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Mark session as 2FA authenticated
        $request->session()->put('two_factor_authenticated', true);

        return response()->json([
            'message' => 'Recovery code accepted',
            'remaining_codes' => count($user->getRecoveryCodes() ?? [])
        ], Response::HTTP_OK);
    }
}
