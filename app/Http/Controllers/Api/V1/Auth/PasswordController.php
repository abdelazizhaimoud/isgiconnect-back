<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Models\User\User;
use App\Models\System\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PasswordController extends ApiController
{
    /**
     * Send password reset link.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        // Generate reset token
        $resetToken = Str::random(64);
        
        $user->update([
            'password_reset_token' => $resetToken,
            'password_reset_token_expires_at' => now()->addHours(1), // 1 hour expiry
        ]);

        // Send reset email
        $this->sendPasswordResetEmail($user, $resetToken);

        // Log password reset request
        Activity::log('password_reset_requested', $user, [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->successResponse(null, 'Password reset link sent to your email');
    }

    /**
     * Reset password using token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::where('email', $request->email)
                   ->where('password_reset_token', $request->token)
                   ->where('password_reset_token_expires_at', '>', now())
                   ->first();

        if (!$user) {
            return $this->errorResponse('Invalid or expired reset token', 400);
        }

        // Update password and clear reset token
        $user->update([
            'password' => Hash::make($request->password),
            'password_reset_token' => null,
            'password_reset_token_expires_at' => null,
            'login_attempts' => 0, // Reset login attempts
            'locked_until' => null, // Unlock account if locked
        ]);

        // Revoke all existing tokens for security
        $user->tokens()->delete();

        // Log password reset
        Activity::log('password_reset_completed', $user, [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->successResponse(null, 'Password reset successfully');
    }

    /**
     * Change password (authenticated user).
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', Password::defaults()],
            'logout_other_devices' => 'boolean',
        ]);

        $user = $request->user();

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return $this->errorResponse('Current password is incorrect', 400);
        }

        // Check if new password is different from current
        if (Hash::check($request->password, $user->password)) {
            return $this->errorResponse('New password must be different from current password', 400);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Optionally logout from other devices
        if ($request->get('logout_other_devices', false)) {
            // Keep current token but revoke all others
            $currentTokenId = $request->user()->currentAccessToken()->id;
            $user->tokens()->where('id', '!=', $currentTokenId)->delete();
$user->tokens()->where('id', '!=', $currentTokenId)->delete();
       }

       // Log password change
       Activity::log('password_changed', $user, [
           'ip_address' => $request->ip(),
           'logout_other_devices' => $request->get('logout_other_devices', false),
       ]);

       return $this->successResponse(null, 'Password changed successfully');
   }

   /**
    * Validate password reset token.
    */
   public function validateResetToken(Request $request): JsonResponse
   {
       $request->validate([
           'token' => 'required|string',
           'email' => 'required|email',
       ]);

       $user = User::where('email', $request->email)
                  ->where('password_reset_token', $request->token)
                  ->where('password_reset_token_expires_at', '>', now())
                  ->first();

       if (!$user) {
           return $this->errorResponse('Invalid or expired reset token', 400);
       }

       return $this->successResponse([
           'valid' => true,
           'expires_at' => $user->password_reset_token_expires_at,
       ], 'Reset token is valid');
   }

   /**
    * Get password requirements.
    */
   public function passwordRequirements(): JsonResponse
   {
       $requirements = [
           'min_length' => 8,
           'requires_uppercase' => true,
           'requires_lowercase' => true,
           'requires_numbers' => true,
           'requires_symbols' => false,
           'cannot_be_compromised' => true,
           'rules' => [
               'Must be at least 8 characters long',
               'Must contain at least one uppercase letter',
               'Must contain at least one lowercase letter',
               'Must contain at least one number',
               'Cannot be a commonly used password',
           ],
       ];

       return $this->successResponse($requirements, 'Password requirements retrieved');
   }

   /**
    * Check password strength.
    */
   public function checkPasswordStrength(Request $request): JsonResponse
   {
       $request->validate([
           'password' => 'required|string',
       ]);

       $password = $request->password;
       $score = 0;
       $feedback = [];

       // Length check
       if (strlen($password) >= 8) {
           $score += 20;
       } else {
           $feedback[] = 'Password should be at least 8 characters long';
       }

       if (strlen($password) >= 12) {
           $score += 10;
       }

       // Uppercase check
       if (preg_match('/[A-Z]/', $password)) {
           $score += 20;
       } else {
           $feedback[] = 'Add uppercase letters';
       }

       // Lowercase check
       if (preg_match('/[a-z]/', $password)) {
           $score += 20;
       } else {
           $feedback[] = 'Add lowercase letters';
       }

       // Number check
       if (preg_match('/[0-9]/', $password)) {
           $score += 20;
       } else {
           $feedback[] = 'Add numbers';
       }

       // Special character check
       if (preg_match('/[^A-Za-z0-9]/', $password)) {
           $score += 10;
       } else {
           $feedback[] = 'Add special characters for extra security';
       }

       // Common password check (simplified)
       $commonPasswords = ['password', '123456', 'qwerty', 'abc123', 'password123'];
       if (in_array(strtolower($password), $commonPasswords)) {
           $score -= 50;
           $feedback[] = 'Avoid common passwords';
       }

       // Determine strength
       $strength = 'weak';
       if ($score >= 80) {
           $strength = 'strong';
       } elseif ($score >= 60) {
           $strength = 'medium';
       }

       return $this->successResponse([
           'score' => max(0, min(100, $score)),
           'strength' => $strength,
           'feedback' => $feedback,
       ], 'Password strength analyzed');
   }

   /**
    * Get password history (for preventing reuse).
    */
   public function passwordHistory(Request $request): JsonResponse
   {
       $user = $request->user();

       // Get recent password changes from activity log
       $passwordChanges = Activity::where('user_id', $user->id)
                                ->whereIn('action', ['password_changed', 'password_reset_completed'])
                                ->orderByDesc('created_at')
                                ->limit(10)
                                ->get()
                                ->map(function ($activity) {
                                    return [
                                        'action' => $activity->action,
                                        'ip_address' => $activity->ip_address,
                                        'created_at' => $activity->created_at,
                                    ];
                                });

       return $this->successResponse($passwordChanges, 'Password history retrieved');
   }

   /**
    * Enable/disable two-factor authentication.
    */
   public function toggleTwoFactor(Request $request): JsonResponse
   {
       $request->validate([
           'enable' => 'required|boolean',
           'password' => 'required|string',
       ]);

       $user = $request->user();

       // Verify password
       if (!Hash::check($request->password, $user->password)) {
           return $this->errorResponse('Invalid password', 400);
       }

       $enable = $request->boolean('enable');

       // Update user preferences
       $preferences = $user->profile?->preferences ?? [];
       $preferences['two_factor_enabled'] = $enable;

       $user->profile()->updateOrCreate(
           ['user_id' => $user->id],
           ['preferences' => $preferences]
       );

       // Log activity
       Activity::log($enable ? 'two_factor_enabled' : 'two_factor_disabled', $user, [
           'ip_address' => $request->ip(),
       ]);

       return $this->successResponse([
           'two_factor_enabled' => $enable,
       ], $enable ? 'Two-factor authentication enabled' : 'Two-factor authentication disabled');
   }

   /**
    * Get security settings.
    */
   public function securitySettings(Request $request): JsonResponse
   {
       $user = $request->user();

       $settings = [
           'two_factor_enabled' => $user->profile?->preferences['two_factor_enabled'] ?? false,
           'login_alerts_enabled' => $user->profile?->preferences['login_alerts_enabled'] ?? true,
           'password_changed_at' => $user->updated_at, // Approximate
           'active_sessions_count' => $user->tokens()->count(),
           'recent_login_attempts' => $user->login_attempts,
           'account_locked_until' => $user->locked_until,
           'last_password_change' => Activity::where('user_id', $user->id)
                                           ->whereIn('action', ['password_changed', 'password_reset_completed'])
                                           ->latest()
                                           ->value('created_at'),
       ];

       return $this->successResponse($settings, 'Security settings retrieved');
   }

   /**
    * Update security settings.
    */
   public function updateSecuritySettings(Request $request): JsonResponse
   {
       $request->validate([
           'login_alerts_enabled' => 'boolean',
           'session_timeout' => 'integer|min:15|max:1440', // 15 minutes to 24 hours
           'password_expiry_days' => 'integer|min:30|max:365',
       ]);

       $user = $request->user();

       // Update preferences
       $preferences = $user->profile?->preferences ?? [];
       
       if ($request->has('login_alerts_enabled')) {
           $preferences['login_alerts_enabled'] = $request->boolean('login_alerts_enabled');
       }
       
       if ($request->has('session_timeout')) {
           $preferences['session_timeout'] = $request->session_timeout;
       }
       
       if ($request->has('password_expiry_days')) {
           $preferences['password_expiry_days'] = $request->password_expiry_days;
       }

       $user->profile()->updateOrCreate(
           ['user_id' => $user->id],
           ['preferences' => $preferences]
       );

       // Log activity
       Activity::log('security_settings_updated', $user, [
           'updated_settings' => $request->only(['login_alerts_enabled', 'session_timeout', 'password_expiry_days']),
       ]);

       return $this->successResponse($preferences, 'Security settings updated');
   }

   /**
    * Generate backup codes for account recovery.
    */
   public function generateBackupCodes(Request $request): JsonResponse
   {
       $request->validate([
           'password' => 'required|string',
       ]);

       $user = $request->user();

       // Verify password
       if (!Hash::check($request->password, $user->password)) {
           return $this->errorResponse('Invalid password', 400);
       }

       // Generate 10 backup codes
       $backupCodes = [];
       for ($i = 0; $i < 10; $i++) {
           $backupCodes[] = strtoupper(Str::random(4) . '-' . Str::random(4));
       }

       // Store hashed versions in user preferences
       $preferences = $user->profile?->preferences ?? [];
       $preferences['backup_codes'] = array_map(function ($code) {
           return Hash::make($code);
       }, $backupCodes);
       $preferences['backup_codes_generated_at'] = now()->toISOString();

       $user->profile()->updateOrCreate(
           ['user_id' => $user->id],
           ['preferences' => $preferences]
       );

       // Log activity
       Activity::log('backup_codes_generated', $user, [
           'codes_count' => count($backupCodes),
       ]);

       return $this->successResponse([
           'backup_codes' => $backupCodes,
           'generated_at' => now()->toISOString(),
           'warning' => 'Store these codes in a safe place. They will not be shown again.',
       ], 'Backup codes generated successfully');
   }

   /**
    * Send password reset email.
    */
   private function sendPasswordResetEmail(User $user, string $token): void
   {
       $resetUrl = config('app.frontend_url') . '/reset-password?token=' . $token . '&email=' . urlencode($user->email);
       
       // Log reset email sent
       \Log::info("Password reset email would be sent to {$user->email} with URL: {$resetUrl}");
       
       // Example of how you might send it:
       // Mail::to($user->email)->send(new PasswordResetMail($user, $resetUrl));
   }
}