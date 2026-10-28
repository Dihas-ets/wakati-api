<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Position;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class PositionController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Position::class);

        $query = Position::with('department:id,name')->withCount('users');

        if ($departmentId = $request->query('department_id')) {
            $query->where('department_id', $departmentId);
        }

        if ($search = $request->query('q')) {
            $query->where('title', 'like', "%{$search}%");
        }

        return response()->json($query->orderBy('title')->get());
    }

    public function store(Request $request)
    {
        $this->authorize('create', Position::class);

        $data = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'title' => ['required', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
            'is_sensitive' => ['nullable', 'boolean'],
        ]);

        $isSensitive = (bool) ($data['is_sensitive'] ?? false);

        // Interdire la création d'un poste sensible par un non-admin
        if ($isSensitive && ! $request->user()->isAdmin()) {
            abort(403, "Action non autorisée. Seul un administrateur peut créer un poste à responsabilité sensible.");
        }

        $position = Position::create([
            'department_id' => $data['department_id'],
            'title' => $data['title'],
            'permissions' => $data['permissions'] ?? [],
            'is_sensitive' => $isSensitive,
        ]);

        if ($isSensitive) {
            AuditLog::record(
                userId: $request->user()->id,
                targetUserId: null,
                action: 'position.sensitive_created',
                details: [
                    'position_id' => $position->id,
                    'title' => $position->title,
                    'department_id' => $position->department_id,
                    'permissions' => $position->permissions,
                ],
                ip: $request->ip()
            );
        }

        return response()->json($position->load('department:id,name'), 201);
    }

    public function show(Position $position)
    {
        $this->authorize('view', $position);

        return response()->json($position->load(['department:id,name', 'users:id,name,email,status']));
    }

    public function update(Request $request, Position $position)
    {
        $this->authorize('update', $position);

        $data = $request->validate([
            'department_id' => ['sometimes', 'integer', 'exists:departments,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
            'is_sensitive' => ['sometimes', 'boolean'],
        ]);

        $targetSensitive = array_key_exists('is_sensitive', $data) ? (bool) $data['is_sensitive'] : $position->is_sensitive;

        // Si le poste est déjà sensible OU qu'on veut le rendre sensible, seul un Admin peut le modifier
        if (($position->is_sensitive || $targetSensitive) && ! $request->user()->isAdmin()) {
            abort(403, "Action non autorisée. Seul un administrateur peut modifier un poste sensible.");
        }

        $wasSensitive = $position->is_sensitive;
        $position->update($data);

        if ($wasSensitive || $position->is_sensitive) {
            AuditLog::record(
                userId: $request->user()->id,
                targetUserId: null,
                action: 'position.sensitive_updated',
                details: [
                    'position_id' => $position->id,
                    'changes' => $data,
                ],
                ip: $request->ip()
            );
        }

        return response()->json($position->load('department:id,name'));
    }

    public function destroy(Request $request, Position $position)
    {
        $this->authorize('delete', $position);

        if ($position->is_sensitive && ! $request->user()->isAdmin()) {
            abort(403, "Action non autorisée. Seul un administrateur peut supprimer un poste sensible.");
        }

        if ($position->is_sensitive) {
            AuditLog::record(
                userId: $request->user()->id,
                targetUserId: null,
                action: 'position.sensitive_deleted',
                details: [
                    'position_id' => $position->id,
                    'title' => $position->title,
                    'department_id' => $position->department_id,
                ],
                ip: $request->ip()
            );
        }

        $position->delete();

        return response()->json(null, 204);
    }
}
