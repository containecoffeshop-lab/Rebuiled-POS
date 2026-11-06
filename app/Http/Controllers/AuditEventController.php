<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Date;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditEventController extends Controller
{
    public function index(Request $request): View|StreamedResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'event' => ['nullable', 'in:POST,PUT,PATCH,DELETE'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'export' => ['nullable', 'in:csv'],
        ]);
        $query = $this->filteredQuery($filters);

        if (($filters['export'] ?? null) === 'csv') {
            return $this->export($query);
        }

        $events = $query->paginate(30)->withQueryString();

        return view('audit.index', compact('events', 'filters'));
    }

    public function show(AuditEvent $auditEvent): Response
    {
        return response()->view('audit.show', ['event' => $auditEvent->load('user')]);
    }

    private function filteredQuery(array $filters): Builder
    {
        return AuditEvent::query()
            ->with('user:id,name,email')
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $term = '%'.$filters['search'].'%';
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('route_name', 'like', $term)
                        ->orWhere('subject_type', 'like', $term)
                        ->orWhere('subject_id', 'like', $term)
                        ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->where('name', 'like', $term)->orWhere('email', 'like', $term));
                });
            })
            ->when($filters['event'] ?? null, fn (Builder $query, string $event) => $query->where('method', $event))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('id');
    }

    private function export(Builder $query): StreamedResponse
    {
        $filename = 'audit-'.$this->todayStamp().'.csv';

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Waktu', 'Pengguna', 'Email', 'Metode', 'Rute', 'Jenis objek', 'ID objek', 'Status HTTP']);

            $query->chunkById(500, function ($events) use ($output): void {
                foreach ($events as $event) {
                    fputcsv($output, [
                        $event->created_at->toIso8601String(),
                        $this->safeCsvText($event->user?->name),
                        $this->safeCsvText($event->user?->email),
                        $event->method,
                        $this->safeCsvText($event->route_name),
                        $this->safeCsvText($event->subject_type ? class_basename($event->subject_type) : ''),
                        $this->safeCsvText($event->subject_id),
                        $event->response_status,
                    ]);
                }
            });

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function safeCsvText(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[\s]*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }

    private function todayStamp(): string
    {
        return Date::now()->format('Ymd-His');
    }
}
