<?php

namespace App\Services;

use App\Models\User;

class ModuleAccess
{
    public const MODULES = [
        'kasir' => 'Kasir dan shift',
        'pesanan' => 'Pesanan dan pembayaran',
        'ringkasan' => 'Ringkasan',
        'konsumsi' => 'Konsumsi karyawan',
        'menu' => 'Menu dan resep',
        'stok' => 'Stok bahan',
        'meja' => 'Meja dan QR',
        'kupon' => 'Kupon',
        'pelanggan' => 'Pelanggan',
        'retur' => 'Retur penjualan',
        'produk' => 'Produk eceran',
        'pembelian' => 'Pembelian dan retur pembelian',
        'pengeluaran' => 'Pengeluaran',
        'laporan' => 'Laporan',
        'promosi' => 'Promo menu',
        'paket' => 'Paket menu',
        'stock_take' => 'Stok opname',
        'stock_audit' => 'Audit stok',
        'barcode' => 'Cetak barcode',
        'audit' => 'Audit aktivitas',
        'cycle_count' => 'Cycle count',
    ];

    public const DEFAULTS = ['kasir', 'pesanan', 'ringkasan', 'konsumsi', 'cycle_count'];

    public static function allows(User $user, string $module): bool
    {
        return $user->role === 'manager' || in_array($module, $user->module_permissions ?? self::DEFAULTS, true);
    }

    public static function forRoute(string $route): ?string
    {
        $routes = [
            'pos.shift.' => 'kasir',
            'pos.orders.pending-count' => 'pesanan',
            'pos.orders.store' => 'kasir',
            'pos.orders.receipt' => 'pesanan',
            'pos.orders.' => 'pesanan',
            'pos.loyalty.' => 'kasir',
            'pos.employee-consumptions.' => 'konsumsi',
            'pos.summary.' => 'laporan',
            'pos.menus.' => 'menu',
            'pos.categories.' => 'menu',
            'pos.materials.' => 'stok',
            'pos.tables.' => 'meja',
            'pos.coupons.' => 'kupon',
            'pos.returns.' => 'retur',
            'pos.products.' => 'produk',
            'pos.product-variants.' => 'produk',
            'pos.purchases.' => 'pembelian',
            'pos.purchase-returns.' => 'pembelian',
            'pos.suppliers.' => 'pembelian',
            'pos.expenses.' => 'pengeluaran',
            'pos.reports.' => 'laporan',
            'pos.promotions.' => 'promosi',
            'pos.bundles.' => 'paket',
            'pos.stock-takes.' => 'stock_take',
            'pos.stock-audits.' => 'stock_audit',
            'pos.barcode-templates.' => 'barcode',
            'pos.audit.' => 'audit',
            'pos.cycle-counts.index' => 'cycle_count',
            'pos.cycle-counts.count' => 'cycle_count',
        ];
        foreach ($routes as $prefix => $module) {
            if (str_starts_with($route, $prefix)) {
                return $module;
            }
        }

        return null;
    }
}
