<?php

namespace App\Http\Controllers;

use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class CompanyUserController extends Controller
{
    // List all users in the authenticated admin's company
    public function index()
    {
        $admin = Auth::user();
        if ($admin->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $users = User::where('company_id', $admin->company_id)->get();
        return response()->json($users);
    }

    // Add/invite a new user to the company
    public function store(Request $request)
    {
        $admin = Auth::user();
        if ($admin->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role' => ['required', Rule::in(['admin', 'recruiter', 'viewer'])],
            'password' => 'required|string|min:6',
        ]);
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'password' => Hash::make($validated['password']),
            'company_id' => $admin->company_id,
            'status' => 'active',
        ]);
        return response()->json($user, 201);
    }

    // Update a user in the company
    public function update(Request $request, $id)
    {
        $admin = Auth::user();
        if ($admin->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $user = User::where('company_id', $admin->company_id)->findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'role' => ['sometimes', Rule::in(['admin', 'recruiter', 'viewer'])],
            'password' => 'nullable|string|min:6',
        ]);
        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }
        $user->update($validated);
        return response()->json($user);
    }

    // Remove a user from the company
    public function destroy($id)
    {
        $admin = Auth::user();
        if ($admin->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $user = User::where('company_id', $admin->company_id)->findOrFail($id);
        $user->delete();
        return response()->json(['message' => 'User deleted']);
    }
} 