<?php

namespace App\Services;

use App\Models\StoreSetting;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SummaryPeriod
{
    private ?array $period;

    public function __construct()
    {
        $value = StoreSetting::query()->where('key', 'summary_period')->value('value');
        $this->period = $value === null ? null : json_decode($value, true, flags: JSON_THROW_ON_ERROR);
    }

    public function start(): ?Carbon
    {
        return $this->period === null ? null : Carbon::parse($this->period['start']);
    }

    public function timestamp(EloquentBuilder|QueryBuilder $query, string $column): void
    {
        if ($start = $this->start()) {
            $query->where($column, '>=', $start);
        }
    }

    public function dated(EloquentBuilder|QueryBuilder $query, string $table, string $dateColumn): void
    {
        if ($start = $this->start()) {
            $query->whereDate($dateColumn, '>=', $start->toDateString())
                ->where($table.'.id', '>', $this->period[$table.'_id']);
        }
    }

    public static function reset(): void
    {
        DB::transaction(function (): void {
            $period = [
                'start' => now()->addSecond()->startOfSecond()->toDateTimeString(),
                'expenses_id' => (int) DB::table('expenses')->max('id'),
                'expense_payments_id' => (int) DB::table('expense_payments')->max('id'),
            ];
            StoreSetting::query()->updateOrCreate(['key' => 'summary_period'], [
                'value' => json_encode($period, JSON_THROW_ON_ERROR),
            ]);
        }, attempts: 3);
    }
}
