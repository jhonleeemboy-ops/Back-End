<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        if (($data['email'] === 'admin@admin.com' || $data['email'] === 'admin') && $data['password'] === 'admin') {
            return response()->json([
                'token' => 'dev-token',
                'user' => ['name' => 'Admin', 'email' => 'admin@admin.com'],
            ], 200);
        }

        return response()->json(['message' => 'Invalid credentials'], 401);
    }
}