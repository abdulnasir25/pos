<?php

namespace App\Modules\AuditLog\Http\Controllers;

use App\Modules\AuditLog\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only. A general listing, not entity-specific — GetAuditTrailFor
 * (used elsewhere for "this Partner's history") stays the tool for
 * that; this page is "everything, newest first," with an optional
 * action filter.
 */
class AuditLogController extends \App\Http\Controllers\Controller
{
    public function show(Request $request): Response
    {
        $action = $request->query('action');

        $query = AuditLog::orderByDesc('id')->limit(200);

        if ($action) {
            $query->where('action', $action);
        }

        $entries = $query->get();
        $userNames = User::pluck('name', 'id');

        return Inertia::render('AuditLog/Index', [
            'filters' => ['action' => $action],
            'actions' => AuditLog::select('action')->distinct()->orderBy('action')->pluck('action'),
            'entries' => $entries->map(fn (AuditLog $entry) => [
                'id' => $entry->id,
                'user' => $entry->user_id ? ($userNames[$entry->user_id] ?? "User #{$entry->user_id}") : 'System',
                'action' => $entry->action,
                'auditable' => $entry->auditable_type ? class_basename($entry->auditable_type).' #'.$entry->auditable_id : null,
                'old_values' => $entry->old_values,
                'new_values' => $entry->new_values,
                'created_at' => $entry->created_at->toDateTimeString(),
            ]),
        ]);
    }
}
