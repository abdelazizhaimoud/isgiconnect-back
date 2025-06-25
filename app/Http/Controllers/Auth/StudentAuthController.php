<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User\User;
use App\Services\TokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\User\Profile;
use App\Http\Requests\Auth\SignupRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class StudentAuthController extends Controller
{
    protected $tokenService;

    public function __construct(
        TokenService $tokenService,
    ) {
        $this->tokenService = $tokenService;
    }

    public function login(Request $request)
    {
        // Credentials-based authentication
        try {
            $credentials = $request->validate([
                'email' => ['required', 'string', 'email'],
                'password' => ['required'],
            ]);


            $user = User::where('email', $credentials['email'])->first();
            if (!$user) {
                return response()->json([
                    'error' => 'Email not found',
                ], 422);
            }
            if (!Hash::check($credentials['password'], $user->password)) {
                return response()->json([
                    'error' => 'Invalid credentials.',
                ], 422);
            }
        
            $user->tokens()->delete();
            $token = $user->createToken('student-token',['can-student-login'],now()->addHour(4))->plainTextToken;
            $user_type = $user->role;

            return response()->json([
                'user_type' => $user_type,
                'user' => $user,
                'token' => $token,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Authentication failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function signup(Request $request)
    {
        return response()->json([
            'data' => 'signup',
        ], 200);
        // try {
        //     $validated = $request->validated(
        //         [
        //             'name' => ['required', 'string', 'max:255'],
        //             'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
        //             'password' => ['required', 'string', 'min:8', 'confirmed'],
        //         ]
        //     );
        //     DB::beginTransaction();

        //     // Create username from name (simple approach for MVP)
        //     $username = $this->generateUsername($validated['name']);

        //     // Create user
        //     $user = User::create([
        //         'name' => $validated['name'],
        //         'username' => $username,
        //         'email' => $validated['email'],
        //         'password' => Hash::make($validated['password']),
        //         'status' => 'active',
        //     ]);

        //     // Create user profile
        //     Profile::create([
        //         'user_id' => $user->id,
        //         'first_name' => $this->extractFirstName($validated['name']),
        //         'last_name' => $this->extractLastName($validated['name']),
        //     ]);

        //     DB::commit();

        //     return response()->json([
        //         'success' => true,
        //         'message' => 'Account created successfully',
        //     ], 201);

        // } catch (\Exception $e) {
        //     DB::rollBack();
            
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Registration failed. Please try again.',
        //         'error' => config('app.debug') ? $e->getMessage() : null
        //     ], 500);
        // }
    }

    /**
     * Generate unique username from name
     */
    private function generateUsername(string $name): string
    {
        $baseUsername = Str::slug(Str::lower($name), '');
        $username = $baseUsername;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $baseUsername . $counter;
            $counter++;
        }
        Log::info($username);

        return $username;
    }

    /**
     * Extract first name from full name
     */
    private function extractFirstName(string $fullName): string
    {
        $parts = explode(' ', trim($fullName));
        return $parts[0] ?? '';
    }

    /**
     * Extract last name from full name
     */
    private function extractLastName(string $fullName): string
    {
        $parts = explode(' ', trim($fullName));
        if (count($parts) > 1) {
            array_shift($parts); // Remove first name
            return implode(' ', $parts);
        }
        return '';
    }


    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'error' => 'User not found',
                ], 404);
            }
            $user->tokens()->delete();
            return response()->json([
                'message' => 'Logged out successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Logout failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getStudentWithToken(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'error' => 'User not found',
                ], 404);
            }
            return response()->json([
                'user' => $user,
                'user_type' => $user->role,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve user',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getAllUsers(Request $request)
    {
        try {
            $users = User::all();
            return response()->json([
                'users' => $users,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve users',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
