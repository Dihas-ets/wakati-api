<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkPattern\StoreWorkPatternRequest;
use App\Http\Requests\WorkPattern\UpdateWorkPatternRequest;
use App\Models\WorkPattern;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class WorkPatternController extends Controller
{
    use AuthorizesRequests;

    /**
     * GET /api/work-patterns
     * RH/Directeur : tous les patterns (filtrables par ?user_id=).
     * Employé : uniquement les siens.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', WorkPattern::class);

        $query = WorkPattern::with(['user:id,name,email', 'createdBy:id,name']);

        if (in_array($request->user()->role, ['employe', 'comptable'])) {
            $query->where('user_id', $request->user()->id);
        } elseif ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        return response()->json(
            $query->orderBy('user_id')->orderBy('day_of_week')->get()
        );
    }

    /**
     * POST /api/work-patterns
     * Crée le planning récurrent d'un employé en une fois.
     * Body : { user_id, effective_from, effective_until?, days: [{ day_of_week, start_time, end_time }] }
     */
    public function store(StoreWorkPatternRequest $request)
    {
        $this->authorize('create', WorkPattern::class);

        $validated = $request->validated();

        if (isset($validated['days'])) {
            $created = collect($validated['days'])->map(fn ($day) => WorkPattern::create([
                'user_id' => $validated['user_id'],
                'created_by' => $request->user()->id,
                'day_of_week' => $day['day_of_week'],
                'start_time' => $day['start_time'],
                'end_time' => $day['end_time'],
                'effective_from' => $validated['effective_from'],
                'effective_until' => $validated['effective_until'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]));
        } else {
            $created = [WorkPattern::create([
                'user_id' => $validated['user_id'],
                'created_by' => $request->user()->id,
                'day_of_week' => $validated['day_of_week'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'effective_from' => $validated['effective_from'],
                'effective_until' => $validated['effective_until'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ])];
        }

        return response()->json([
            'message' => 'Planning récurrent créé.',
            'work_patterns' => $created,
        ], 201);
    }

    /**
     * PUT /api/work-patterns/{workPattern}
     */
    public function update(UpdateWorkPatternRequest $request, WorkPattern $workPattern)
    {
        $this->authorize('update', $workPattern);

        $validated = $request->validated();

        $workPattern->update($validated);

        return response()->json([
            'message' => 'Planning récurrent mis à jour.',
            'work_pattern' => $workPattern,
        ]);
    }

    /**
     * DELETE /api/work-patterns/{workPattern}
     */
    public function destroy(WorkPattern $workPattern)
    {
        $this->authorize('delete', $workPattern);

        $workPattern->delete();

        return response()->json(['message' => 'Créneau récurrent supprimé.']);
    }
}