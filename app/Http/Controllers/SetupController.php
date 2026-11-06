<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (DB::table('setup_state')->where('id', 1)->value('is_complete')) {
            return redirect()->route('login');
        }

        return view('auth.setup');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        DB::transaction(function () use ($data): void {
            $claimed = DB::table('setup_state')
                ->where('id', 1)
                ->where('is_complete', false)
                ->update(['is_complete' => true]);

            abort_unless($claimed === 1, 403, 'Pengaturan awal sudah diselesaikan.');

            User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
        });

        Auth::login(User::query()->where('email', $data['email'])->firstOrFail());
        $request->session()->regenerate();

        return redirect()->route('pos.index')->with('success', 'Akun pengelola berhasil dibuat.');
    }
}
