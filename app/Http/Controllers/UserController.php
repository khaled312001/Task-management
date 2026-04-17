<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function index()
    {
        return response()->json(
            User::where('is_active', true)->select('id', 'username', 'email', 'full_name', 'role', 'avatar_color')->get()
        );
    }

    public function store(Request $request)
    {
        if (!in_array(Auth::user()->role, ['admin', 'manager'])) {
            return response()->json(['error' => 'Permission denied'], 403);
        }

        $request->validate([
            'username' => 'required|string|unique:users',
            'full_name' => 'required|string',
            'password' => 'required|string|min:4',
        ]);

        $user = User::create([
            'username' => $request->username,
            'email' => $request->email ?? '',
            'password' => $request->password,
            'full_name' => $request->full_name,
            'role' => $request->role ?? 'developer',
            'avatar_color' => $request->avatar_color ?? '#6366f1',
        ]);

        return response()->json($user, 201);
    }
}
