<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) return redirect()->route('home');
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $field = filter_var($request->username, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (Auth::attempt([$field => $request->username, 'password' => $request->password, 'is_active' => true], $request->boolean('remember'))) {
            $request->session()->regenerate();
            return response()->json(['success' => true, 'user' => Auth::user()]);
        }

        return response()->json(['error' => 'بيانات الدخول غير صحيحة'], 401);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    public function me()
    {
        return response()->json(Auth::user());
    }
}
