<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    private const WEAK_PINS = ['0000','1111','2222','3333','4444','5555','6666','7777','8888','9999','1234','4321','0123','1212'];

    public function show()
    {
        $staff = auth()->user()->isAdmin()
            ? User::orderBy('name')->get(['id', 'name', 'role', 'override_pin_hash'])
                ->map(fn ($u) => (object) [
                    'name'    => $u->name,
                    'role'    => $u->role,
                    'pin_set' => $u->override_pin_hash !== null,
                ])
            : null;

        return view('account', compact('staff'));
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required',
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        if (! Hash::check($data['current_password'], auth()->user()->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        auth()->user()->update(['password' => Hash::make($data['password'])]);

        return redirect()->route('account')->with('success', 'Password updated.');
    }

    public function updatePin(Request $request)
    {
        $request->validate([
            'pin'               => 'required|digits_between:4,6|confirmed',
            'current_pin'       => 'nullable|string',
            'current_password'  => 'nullable|string',
        ]);

        $pin  = $request->input('pin');
        $user = auth()->user();

        if (in_array($pin, self::WEAK_PINS, true)) {
            return back()->withErrors(['pin' => 'This PIN is too easy to guess. Choose a different one.']);
        }

        if ($user->override_pin_hash !== null) {
            $verifiedByPin      = $request->filled('current_pin') && Hash::check($request->input('current_pin'), $user->override_pin_hash);
            $verifiedByPassword = $request->filled('current_password') && Hash::check($request->input('current_password'), $user->password);

            if (! $verifiedByPin && ! $verifiedByPassword) {
                return back()->withErrors(['current_pin' => 'Current PIN (or account password) is incorrect.']);
            }
        }

        $user->update(['override_pin_hash' => Hash::make($pin)]);

        return redirect()->route('account')->with('success', 'Override PIN saved.');
    }
}
