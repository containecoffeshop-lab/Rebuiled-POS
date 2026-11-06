<?php

namespace App\Http\Middleware;

use App\Models\AuditEvent;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordAuditEvent
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $response;
        }

        $route = $request->route();
        $subject = collect($route?->parameters() ?? [])->first(fn ($value) => $value instanceof Model);
        $properties = [
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
        ];

        if ($subject) {
            $properties['subject'] = class_basename($subject);
        }

        AuditEvent::create([
            'user_id' => $request->user()?->id,
            'route_name' => $route?->getName(),
            'method' => $request->method(),
            'response_status' => $response->getStatusCode(),
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey() === null ? null : (string) $subject->getKey(),
            'properties' => $properties,
        ]);

        return $response;
    }
}
