<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Models\User\User;
use App\Models\User\UserToken;
use App\Models\System\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class AuthController extends ApiController
{
    /**
     * Register a new user.
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Generate email verification token
        $verificationToken = Str::random(64);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'status' => 'pending_verification',
            'email_verification_token' => $verificationToken,
            'email_verification_token_expires_at' => now()->addHours(24),
        ]);

        // Create user profile
        $user->profile()->create([
            'first_name' => $request->first_name ?? '',
            'last_name' => $request->last_name ?? '',
        ]);

        // Assign default role
        $defaultRole = \App\Models\User\Role::where('is_default', true)->first();
        if ($defaultRole) {
            $user->assignRole($defaultRole);
        }

        // Send verification email
        $this->sendVerificationEmail($user, $verificationToken);

        // Log registration activity
        Activity::logCreated($user, [
            'registration_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->successResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
            ],
            'message' => 'Registration successful. Please check your email to verify your account.',
        ], 'User registered successfully', 201);
    }

    /**
     * Login user.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'remember_me' => 'boolean',
        ]);

        $user = User::where('email', $request->email)->first();

        // Check if user exists
        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if account is locked
        if ($user->isLocked()) {
            return $this->errorResponse('Account is temporarily locked due to too many failed attempts.', 423);
        }

        // Check password
        if (!Hash::check($request->password, $user->password)) {
            $user->increment('login_attempts');
            
            // Lock account after 5 failed attempts
            if ($user->login_attempts >= 5) {
                $user->update(['locked_until' => now()->addMinutes(30)]);
            }

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if account is active
        if (!$user->isActive()) {
            return $this->errorResponse('Account is not active. Please contact support.', 403);
        }

        // Check if email is verified
        if (!$user->hasVerifiedEmail()) {
            return $this->errorResponse('Please verify your email address before logging in.', 403);
        }

        // Reset login attempts and update login info
        $user->update([
            'login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        // Create token
        $tokenName = $request->get('device_name', 'api-token');
        $abilities = ['*']; // Default abilities
        $expiresAt = $request->remember_me ? now()->addDays(30) : now()->addHours(24);

        $token = $user->createToken($tokenName, $abilities, $expiresAt);

        // Store additional token info
        UserToken::create([
            'user_id' => $user->id,
            'token_type' => 'api',
            'token' => hash('sha256', $token->plainTextToken),
            'abilities' => $abilities,
            'name' => $tokenName,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'expires_at' => $expiresAt,
        ]);

        // Log login activity
        Activity::log('logged_in', $user, [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $user->load(['profile', 'roles']);

        return $this->successResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'email_verified_at' => $user->email_verified_at,
                'profile' => $user->profile,
                'roles' => $user->roles->pluck('name'),
                'permissions' => $user->getPermissionSlugs(),
            ],
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt->toISOString(),
        ], 'Login successful');
    }

    /**
     * Logout user.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        // Log logout activity
        Activity::log('logged_out', $user, [
            'ip_address' => $request->ip(),
        ]);

        return $this->successResponse(null, 'Logged out successfully');
    }

    /**
     * Logout from all devices.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Revoke all tokens
        $user->tokens()->delete();

        // Mark all user tokens as revoked
        UserToken::where('user_id', $user->id)->update([
            'is_revoked' => true,
            'revoked_at' => now(),
        ]);

        // Log logout activity
        Activity::log('logged_out_all_devices', $user, [
            'ip_address' => $request->ip(),
        ]);

        return $this->successResponse(null, 'Logged out from all devices successfully');
    }

    /**
     * Get current user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['profile', 'roles.permissions']);

        return $this->successResponse([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'email_verified_at' => $user->email_verified_at,
            'last_login_at' => $user->last_login_at,
            'profile' => $user->profile,
            'roles' => $user->roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                ];
            }),
            'permissions' => $user->getPermissionSlugs(),
        ], 'User profile retrieved successfully');
    }

    /**
     * Verify email address.
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $user = User::where('email_verification_token', $request->token)
                   ->where('email_verification_token_expires_at', '>', now())
                   ->first();

        if (!$user) {
            return $this->errorResponse('Invalid or expired verification token.', 400);
        }

        $user->update([
            'email_verified_at' => now(),
            'email_verification_token' => null,
            'email_verification_token_expires_at' => null,
            'status' => 'active',
        ]);

        // Log verification activity
        Activity::log('email_verified', $user);

        return $this->successResponse(null, 'Email verified successfully');
    }

    /**
     * Resend verification email.
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->hasVerifiedEmail()) {
            return $this->errorResponse('Email is already verified.', 400);
        }

        // Generate new verification token
        $verificationToken = Str::random(64);
        $user->update([
            'email_verification_token' => $verificationToken,
            'email_verification_token_expires_at' => now()->addHours(24),
        ]);

        // Send verification email
        $this->sendVerificationEmail($user, $verificationToken);

        return $this->successResponse(null, 'Verification email sent successfully');
    }

    /**
     * Refresh token.
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentToken = $request->user()->currentAccessToken();

        // Create new token
        $tokenName = $currentToken->name ?? 'api-token';
        $abilities = $currentToken->abilities ?? ['*'];
        $expiresAt = now()->addHours(24);

        $newToken = $user->createToken($tokenName, $abilities, $expiresAt);

        // Revoke old token
        $currentToken->delete();

        // Store additional token info
        UserToken::create([
            'user_id' => $user->id,
            'token_type' => 'api',
            'token' => hash('sha256', $newToken->plainTextToken),
            'abilities' => $abilities,
            'name' => $tokenName,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'expires_at' => $expiresAt,
        ]);

        return $this->successResponse([
            'token' => $newToken->plainTextToken,
            'expires_at' => $expiresAt->toISOString(),
        ], 'Token refreshed successfully');
    }

    /**
     * Get user's active sessions/tokens.
     */
    public function sessions(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $sessions = UserToken::where('user_id', $user->id)
                            ->where('is_revoked', false)
                            ->where('token_type', 'api')
                            ->orderByDesc('created_at')
                            ->get()
                            ->map(function ($token) {
                                return [
                                    'id' => $token->id,
                                    'name' => $token->name,
                                    'ip_address' => $token->ip_address,
                                    'user_agent' => $token->user_agent,
                                    'last_used_at' => $token->last_used_at,
                                    'expires_at' => $token->expires_at,
                                    'created_at' => $token->created_at,
                                ];
                            });

        return $this->successResponse($sessions, 'Active sessions retrieved successfully');
    }

    /**
     * Revoke a specific session/token.
     */
    public function revokeSession(Request $request, $tokenId): JsonResponse
    {
        $user = $request->user();
        
        $userToken = UserToken::where('id', $tokenId)
                             ->where('user_id', $user->id)
                             ->first();

        if (!$userToken) {
            return $this->errorResponse('Session not found.', 404);
        }

        $userToken->revoke();

        // Also revoke from Sanctum
        $user->tokens()->where('id', $tokenId)->delete();

        return $this->successResponse(null, 'Session revoked successfully');
    }

    /**
     * Send verification email.
     */
    private function sendVerificationEmail(User $user, string $token): void
    {
        $verificationUrl = config('app.frontend_url') . '/verify-email?token=' . $token;
        
        // Here you would send the actual email
        // For now, we'll just log it
        Log::info("Verification email would be sent to {$user->email} with URL: {$verificationUrl}");
        
        // Example of how you might send it:
        // Mail::to($user->email)->send(new VerifyEmailMail($user, $verificationUrl));
    }
}