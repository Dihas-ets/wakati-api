<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    /**
     * Liste publique des forfaits actifs disponibles pour l'inscription et la landing page.
     */
    public function index(): JsonResponse
    {
        $plans = Plan::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('price', 'asc')
            ->get();

        return response()->json($plans);
    }
}
