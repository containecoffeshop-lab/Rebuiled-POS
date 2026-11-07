<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\StoreCharge;
use App\Models\StoreSetting;
use App\Services\OptimizedImageUpload;
use App\Services\PosNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StoreSettingsController extends Controller
{
    public function updateShiftTimes(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'shift_1_start' => ['nullable', 'date_format:H:i', 'required_with:shift_1_end'],
            'shift_1_end' => ['nullable', 'date_format:H:i', 'required_with:shift_1_start'],
            'shift_2_start' => ['nullable', 'date_format:H:i', 'required_with:shift_2_end'],
            'shift_2_end' => ['nullable', 'date_format:H:i', 'required_with:shift_2_start'],
        ]);
        DB::transaction(function () use ($data): void {
            foreach (['shift_1_start', 'shift_1_end', 'shift_2_start', 'shift_2_end'] as $key) {
                StoreSetting::query()->updateOrCreate(['key' => $key], ['value' => $data[$key] ?? '']);
            }
        });

        return back()->with('success', 'Jadwal shift berhasil disimpan.');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'categories' => ['nullable', 'array:manager,cashier'],
            'categories.manager' => ['nullable', 'array'],
            'categories.cashier' => ['nullable', 'array'],
            'categories.*.*' => ['string', Rule::in(array_keys(PosNotificationService::CATEGORIES))],
        ]);

        DB::transaction(function () use ($data): void {
            foreach (array_keys(PosNotificationService::ROLES) as $role) {
                StoreSetting::query()->updateOrCreate(
                    ['key' => 'notification_categories_'.$role],
                    ['value' => json_encode(array_values($data['categories'][$role] ?? []), JSON_THROW_ON_ERROR)],
                );
            }
        });

        return back()->with('success', 'Pengaturan notifikasi berhasil disimpan.');
    }

    public function index(): View
    {
        $settings = StoreSetting::query()->pluck('value', 'key');
        $charges = StoreCharge::query()->orderBy('id')->get();
        $bankAccounts = BankAccount::query()->orderBy('bank_name')->orderBy('account_name')->get();

        return view('settings.index', compact('settings', 'charges', 'bankAccounts'));
    }

    public function update(Request $request, OptimizedImageUpload $images): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:40'],
            'receipt_footer' => ['nullable', 'string', 'max:240'],
            'hero_title' => ['nullable', 'string', 'max:120'],
            'hero_description' => ['nullable', 'string', 'max:300'],
            'brand_primary' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'brand_accent' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'brand_background' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=64,min_height=64,max_width=5000,max_height=5000'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        $oldLogoPath = StoreSetting::query()->where('key', 'logo_path')->value('value');
        $newLogoPath = isset($data['logo']) ? $images->store($data['logo'], 'store', 512, 'logo') : null;
        unset($data['logo'], $data['remove_logo']);
        $existingSettings = StoreSetting::query()->pluck('value', 'key');
        foreach ([
            'brand_primary' => '#493123',
            'brand_accent' => '#947456',
            'brand_background' => '#faf8f5',
        ] as $key => $default) {
            $data[$key] ??= $existingSettings->get($key, $default);
        }
        $values = [
            ...$data,
            'logo_path' => $newLogoPath ?? ($request->boolean('remove_logo') ? '' : (string) $oldLogoPath),
        ];

        try {
            DB::transaction(function () use ($values): void {
                foreach ($values as $key => $value) {
                    StoreSetting::query()->updateOrCreate(['key' => $key], ['value' => trim((string) ($value ?? ''))]);
                }
            }, attempts: 3);
        } catch (\Throwable $exception) {
            $images->delete($newLogoPath);
            throw $exception;
        }

        if ($newLogoPath || $request->boolean('remove_logo')) {
            $images->delete($oldLogoPath);
        }

        return back()->with('success', 'Identitas dan tampilan toko berhasil disimpan.');
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
