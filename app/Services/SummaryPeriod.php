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
            if (! array_key_exists('transaction_id', $this->period) || ! array_key_exists('paid_at_start_ids', $this->period)) {
                $query->where($column, '>=', $start);

                return;
            }
            $paid = str_ends_with($column, 'paid_at');
            $query->where(function ($query) use ($column, $start, $paid): void {
                $query->where($column, '>', $start)
                    ->orWhere(function ($query) use ($column, $start, $paid): void {
                        $query->where($column, '=', $start);
                        if ($paid) {
                            $query->whereNotIn('transactions.id', $this->period['paid_at_start_ids']);
                        } else {
                            $query->where('transactions.id', '>', $this->period['transaction_id']);
                        }
                    });
            });
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
            $start = now()->startOfSecond();
            $period = [
                'start' => $start->toDateTimeString(),
                'transaction_id' => (int) DB::table('transactions')->max('id'),
                'paid_at_start_ids' => DB::table('transactions')->where('status', 'paid')->where('paid_at', $start)->pluck('id')->all(),
                'expenses_id' => (int) DB::table('expenses')->max('id'),
                'expense_payments_id' => (int) DB::table('expense_payments')->max('id'),
            ];
            StoreSetting::query()->updateOrCreate(['key' => 'summary_period'], [
                'value' => json_encode($period, JSON_THROW_ON_ERROR),
            ]);
        }, attempts: 3);
    }
}
