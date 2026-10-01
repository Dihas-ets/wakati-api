<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExplanationRequest\RespondExplanationRequest;
use App\Models\ExplanationRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class ExplanationRequestController extends Controller
{
    use AuthorizesRequests;

    /**
     * GET /api/explanation-requests
     * RH/Directeur : toutes les demandes récentes (pour voir les réponses).
     * Employé/Comptable : uniquement les leurs (pour répondre).
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', ExplanationRequest::class);

        $query = ExplanationRequest::with('user:id,name,email');

        if (in_array($request->user()->role, ['employe', 'comptable'])) {
            $query->where('user_id', $request->user()->id);
        }

        return response()->json($query->orderBy('sent_at', 'desc')->limit(30)->get());
    }

    /**
     * POST /api/explanation-requests/{explanationRequest}/respond
     */
    public function respond(RespondExplanationRequest $request, ExplanationRequest $explanationRequest)
    {
        $this->authorize('respond', $explanationRequest);

        $validated = $request->validated();

        $explanationRequest->update([
            'response' => $validated['response'],
            'responded_at' => now(),
        ]);

        return response()->json([
            'message' => 'Réponse envoyée au RH et au Directeur.',
            'explanation_request' => $explanationRequest,
        ]);
    }
}