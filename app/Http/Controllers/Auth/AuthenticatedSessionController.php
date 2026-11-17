<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login-live');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'The provided credentials are incorrect.']);
        }

        if (! $request->user()?->isActive()) {
            Auth::logout();

            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'This account is inactive. Please contact the Municipal Agriculture Office.']);
        }

        $request->session()->regenerate();

        if ($request->user()->hasRole('farmer')) return redirect()->route('farmer.portal');
        if ($request->user()->isSuperAdministrator()) return redirect()->route('admin.dashboard');
        if ($request->user()->hasRole('mao_admin') || $request->user()->hasRole('municipal_agriculturist') || $request->user()->hasRole('module_personnel')) {
            return redirect()->route('mao.dashboard');
        }
        if ($request->user()->hasRole('authorized_viewer') || $request->user()->canErp('dashboard.view')) {
            return redirect()->route('dashboard');
        }
        Auth::logout();
        return redirect()->route('login')->withErrors(['email' => 'This account has no authorized portal assignment.']);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
