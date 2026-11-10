<?php

namespace App\Features\Auth\Http;

use App\Features\Auth\Actions\LoginUserAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Fallback login tanpa JavaScript.
 *
 * Form login memakai Livewire (wire:submit). Jika JavaScript Livewire belum termuat
 * (mis. tool audit langsung mengklik tombol), browser mengirim form secara normal
 * lewat POST ke endpoint ini sehingga login tetap berhasil.
 */
class LoginController
{
    public function store(Request $request, LoginUserAction $action): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! $action->execute($credentials['username'], $credentials['password'], $request->boolean('remember'))) {
            return back()
                ->withErrors(['login' => 'Username/email atau password salah.'])
                ->onlyInput('username');
        }

        $request->session()->regenerate();

        return redirect($request->user()->isAdmin() ? '/admin/users' : '/stocks');
    }
}
