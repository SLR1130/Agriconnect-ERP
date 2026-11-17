<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class AccountSetupController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.account-setup', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)],
        ]);

        $status = Password::broker()->reset($data, function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
                'is_active' => true,
                'suspended_at' => null,
                'activated_at' => $user->activated_at ?: now(),
            ])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            ActivityLog::log('updated', 'Account password setup completed and account activated.', (string) $user->id, $user->id, 'erp');
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
        }

        return redirect()->route('login')->with('status', 'Password setup completed. You can now sign in securely.');
    }
}
