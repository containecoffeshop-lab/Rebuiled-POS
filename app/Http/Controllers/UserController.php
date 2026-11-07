<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\CashierShift;
use App\Services\ModuleAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function updatePermissions(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(array_keys(ModuleAccess::MODULES))],
        ]);
        if ($user->role !== 'cashier') {
            throw ValidationException::withMessages(['permissions' => 'Hak akses modul hanya dapat diubah untuk akun kasir.']);
        }
        $user->update(['module_permissions' => array_values($data['permissions'] ?? [])]);

        return back()->with('success', 'Akses modul berhasil disimpan.');
    }

    public function index(Request $request): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120']]);
        $users = User::query()
            ->when(filled($filters['search'] ?? null), fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                $query->where('name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('email', 'like', '%'.$filters['search'].'%');
            }))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $openShifts = CashierShift::query()->with('user')->whereNull('closed_at')->orderBy('opened_at')->get();

        return view('users.index', compact('users', 'filters', 'openShifts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(['manager', 'cashier'])],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);
        User::create($data + ['is_active' => true]);

        return back()->with('success', 'Akun pengguna berhasil dibuat.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(['manager', 'cashier'])],
            'password' => ['nullable', 'string', 'min:10', 'confirmed'],
        ]);

        if ($user->is($request->user()) && $data['role'] !== 'manager') {
            throw ValidationException::withMessages(['role' => 'Akun yang sedang digunakan tidak dapat menurunkan hak aksesnya sendiri.']);
        }

        DB::transaction(function () use ($data, $user): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $demotesActiveManager = $locked->role === 'manager'
                && $locked->is_active
                && $data['role'] !== 'manager';

            if ($demotesActiveManager && $this->activeManagerCount() <= 1) {
                throw ValidationException::withMessages(['role' => 'Simpan setidaknya satu akun pengelola aktif.']);
            }

            $locked->update(collect($data)->reject(fn ($value, $key) => $key === 'password' && ! filled($value))->all());
        }, attempts: 3);

        return back()->with('success', 'Data pengguna berhasil diperbarui.');
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['user' => 'Akun yang sedang digunakan tidak dapat dinonaktifkan.']);
        }

        DB::transaction(function () use ($user): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($locked->is_active && $locked->role === 'manager' && $this->activeManagerCount() <= 1) {
                throw ValidationException::withMessages(['user' => 'Simpan setidaknya satu akun pengelola aktif.']);
            }

            $locked->update(['is_active' => ! $locked->is_active]);
        }, attempts: 3);

        return back()->with('success', $user->is_active ? 'Akun pengguna dinonaktifkan.' : 'Akun pengguna diaktifkan kembali.');
    }

    private function activeManagerCount(): int
    {
        return User::query()
            ->where('role', 'manager')
            ->where('is_active', true)
            ->lockForUpdate()
            ->get(['id'])
            ->count();
    }
}
