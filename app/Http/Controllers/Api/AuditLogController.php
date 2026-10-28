<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        if (! $request->user()->isAdmin()) {
            abort(403, "Seul un administrateur peut consulter le journal d'audit.");
        }

        $query = AuditLog::with(['user:id,name,email', 'targetUser:id,name,email'])
            ->orderBy('created_at', 'desc');

        if ($action = $request->query('action')) {
            $query->where('action', 'like', "%{$action}%");
        }

        if ($targetId = $request->query('target_user_id')) {
            $query->where('target_user_id', $targetId);
        }

        return response()->json($query->paginate(25));
    }
}
