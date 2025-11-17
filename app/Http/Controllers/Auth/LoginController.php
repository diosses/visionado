<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Show login form - redirect if already logged in
     */
    public function showLoginForm()
    {
        // Manually redirect if already logged in
        if (Auth::check()) {
            return redirect(Auth::user()->isAdmin() ? '/dashboard/admin' : '/dashboard/user');
        }
        
        return view('auth.login');
    }

    /**
     * Handle login attempt
     */
    public function login(Request $request)
    {
        // Validate credentials
        $credentials = $request->validate([
            'email' => ['required','email'],
            'password' => ['required'],
        ]);

        // Attempt login
        if (Auth::attempt($credentials, true)) {
            $request->session()->regenerate();
            
            // Simple role-based redirect
            $redirectPath = Auth::user()->isAdmin() ? '/dashboard/admin' : '/dashboard/user';
            return redirect($redirectPath);
        }

        // Invalid credentials
        return back()->withErrors([
            'email' => 'Estas credenciales no coinciden con nuestros registros.',
        ])->onlyInput('email');
    }

    /**
     * Handle logout
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect('/');
    }
}
