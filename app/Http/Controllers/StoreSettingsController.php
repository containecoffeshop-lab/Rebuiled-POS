<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\StoreCharge;
use App\Models\StoreSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StoreSettingsController extends Controller
{
    public function index(): View
    {
        $settings = StoreSetting::query()->pluck('value', 'key');
        $charges = StoreCharge::query()->orderBy('id')->get();
        $bankAccounts = BankAccount::query()->orderBy('bank_name')->orderBy('account_name')->get();

        return view('settings.index', compact('settings', 'charges', 'bankAccounts'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:40'],
            'receipt_footer' => ['nullable', 'string', 'max:240'],
        ]);

        DB::transaction(function () use ($data): void {
            foreach (['name', 'address', 'phone', 'receipt_footer'] as $key) {
                StoreSetting::query()->updateOrCreate(['key' => $key], ['value' => trim($data[$key] ?? '')]);
            }
        }, attempts: 3);

        return back()->with('success', 'Pengaturan toko berhasil disimpan.');
    }

    public function updateCharges(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'charges' => ['nullable', 'array', 'max:10'],
            'charges.*.code' => ['required', 'in:PJK,OPR'],
            'charges.*.name' => ['required', 'string', 'max:120', 'not_regex:/^\\s*$/'],
            'charges.*.value' => ['required', 'string', 'regex:/^(?:\\d{1,12}|\\d{1,3}(?:\\.\\d{1,2})?%)$/'],
            'charges.*.is_active' => ['required', 'boolean'],
        ]);

        foreach ($data['charges'] ?? [] as $index => $charge) {
            if (str_ends_with($charge['value'], '%') && (float) substr($charge['value'], 0, -1) > 100) {
                throw ValidationException::withMessages([
                    "charges.$index.value" => 'Persentase pajak atau biaya layanan maksimal 100%.',
                ]);
            }
        }

        DB::transaction(function () use ($data): void {
            StoreCharge::query()->delete();

            foreach ($data['charges'] ?? [] as $charge) {
                StoreCharge::create([
                    ...$charge,
                    'name' => trim($charge['name']),
                    'value' => trim($charge['value']),
                    'is_active' => (bool) $charge['is_active'],
                ]);
            }
        }, attempts: 3);

        return back()->with('success', 'Pengaturan pajak dan biaya layanan berhasil disimpan.');
    }

    public function updateLoyalty(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'earning_threshold' => ['nullable', 'integer', 'min:1', 'max:999999999999'],
            'point_value' => ['nullable', 'integer', 'min:1', 'max:999999999999'],
        ]);

        DB::transaction(function () use ($data): void {
            foreach ([
                'loyalty_earning_threshold' => $data['earning_threshold'] ?? '',
                'loyalty_point_value' => $data['point_value'] ?? '',
            ] as $key => $value) {
                StoreSetting::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
            }
        }, attempts: 3);

        return back()->with('success', 'Pengaturan poin loyalitas berhasil disimpan. Nilai kosong menonaktifkan bagian terkait.');
    }

    public function updateBankAccounts(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'accounts' => ['nullable', 'array', 'max:20'],
            'accounts.*.id' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'accounts.*.bank_name' => ['required', 'string', 'max:80', 'not_regex:/^\\s*$/'],
            'accounts.*.account_number' => ['required', 'string', 'max:80', 'not_regex:/^\\s*$/'],
            'accounts.*.account_name' => ['required', 'string', 'max:120', 'not_regex:/^\\s*$/'],
            'accounts.*.is_active' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($data): void {
            foreach ($data['accounts'] ?? [] as $accountData) {
                $id = $accountData['id'] ?? null;
                unset($accountData['id']);
                $accountData['bank_name'] = trim($accountData['bank_name']);
                $accountData['account_number'] = trim($accountData['account_number']);
                $accountData['account_name'] = trim($accountData['account_name']);
                $accountData['is_active'] = (bool) $accountData['is_active'];

                if ($id) {
                    BankAccount::query()->lockForUpdate()->findOrFail($id)->update($accountData);
                } else {
                    BankAccount::create($accountData);
                }
            }
        }, attempts: 3);

        return back()->with('success', 'Rekening transfer berhasil disimpan. Nonaktifkan rekening lama agar tetap tercatat di transaksi terdahulu.');
    }
}
