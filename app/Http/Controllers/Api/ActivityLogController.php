<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    
    public function index(Request $request)
    {
        $query = ActivityLog::with('user:id,name,email')->orderBy('created_at', 'desc');

        if ($request->filled('action')) {
            $query->where('action', 'like', $request->query('action').'%');
        }

        return response()->json($query->limit(100)->get());
    }
}