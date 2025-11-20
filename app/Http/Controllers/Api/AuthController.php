<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Controller;

class AuthController extends Controller
{
    /**
     * Register a new user
     * - Validates user input
     * - Assigns role based on email domain (@admin.com, @dealer.com, default customer)
     * - Creates user in database
     */
    public function register(Request $request)
    {
        // Validate registration input
        $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|string|email|unique:users',
            'password'              => 'required|string|min:6|confirmed', // must match password_confirmation
        ]);

        $email = $request->email;
        $role = 'customer'; // default role if no matching email domain

        // Assign role based on email domain
        if (str_ends_with($email, '@admin.com')) {
            $role = 'admin';
        } elseif (str_ends_with($email, '@dealer.com')) {
            $role = 'dealer';
        }

        // Create user in database
        $user = User::create([
            'name'     => $request->name,
            'email'    => $email,
            'password' => Hash::make($request->password), // hash password for security
            'role'     => $role,
        ]);

        // Return created user
        return response()->json($user, 201);
    }

    /**
     * Login a user
     * - Validates credentials
     * - Checks if email/password match
     * - Creates and returns API token
     */
    public function login(Request $request)
    {
        // Validate login input
        $request->validate([
            'email'    => 'required|string|email',
            'password' => 'required|string',
        ]);

        // Find user by email
        $user = User::where('email', $request->email)->first();

        // If no user or password mismatch, return error
        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials'],
            ]);
        }

        // Create Sanctum token for authenticated user
        $token = $user->createToken('auth_token')->plainTextToken;

        // Return token + user details
        return response()->json([
            'token' => $token,
            'user'  => $user,
        ]);
    }

    /**
     * Logout a user
     * - Deletes all active tokens (forces re-login)
     */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete(); // revoke all tokens
        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Show current authenticated user's profile
     */
    public function profile(Request $request)
    {
        return $request->user(); // returns logged-in user
    }
}