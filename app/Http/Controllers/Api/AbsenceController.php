<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Absence\JustifyAbsenceRequest;
use App\Models\Absence;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class AbsenceController extends Controller
{
    use AuthorizesRequests;

    /**
     * GET /api/absences?from=&to=&user_id=
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Absence::class);

        $query = Absence::with(['user:id,name,email']);

        if (in_array($request->user()->role, ['employe', 'comptable'])) {
            $query->where('user_id', $request->user()->id);
        } elseif ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('from')) {
            $query->where('date', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->where('date', '<=', $request->to);
        }

        $query->orderBy('created_at', 'desc');

        if (! $request->has('from') && ! $request->has('to')) {
            $query->limit(20);
        }

        return response()->json($query->get());
    }

    /**
     * PATCH /api/absences/{absence}/justify
     * Réservé RH/Directeur (voir routes/api.php).
     */
    public function justify(JustifyAbsenceRequest $request, Absence $absence)
    {
        $this->authorize('justify', $absence);

        $validated = $request->validated();

        $absence->update([
            'status' => 'justified',
            'reason' => $validated['reason'],
        ]);

        return response()->json([
            'message' => 'Absence justifiée.',
            'absence' => $absence,
        ]);
    }
}