<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Department\StoreDepartmentRequest;
use App\Http\Requests\Department\UpdateDepartmentRequest;
use App\Models\Department;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    use AuthorizesRequests;

    /**
     * GET /api/departments
     * Liste simple, utilisée pour remplir le <select> département
     * dans le formulaire d'ajout/édition d'employé.
     */
    public function index()
    {
        $this->authorize('viewAny', Department::class);

        return response()->json(Department::orderBy('name')->get(['id', 'name']));
    }

    /**
     * POST /api/departments
     * Réservé à rh/directeur.
     */
    public function store(StoreDepartmentRequest $request)
    {
        $this->authorize('create', Department::class);

        $department = Department::create($request->validated());

        return response()->json($department, 201);
    }

    /**
     * PUT/PATCH /api/departments/{department}
     * Réservé à rh/directeur.
     */
    public function update(UpdateDepartmentRequest $request, Department $department)
    {
        $this->authorize('update', $department);

        $department->update($request->validated());

        return response()->json($department);
    }

    /**
     * DELETE /api/departments/{department}
     * Réservé à rh/directeur.
     * Les employés rattachés passent à department_id = null (nullOnDelete).
     */
    public function destroy(Department $department)
    {
        $this->authorize('delete', $department);

        $department->delete();

        return response()->json(null, 204);
    }
}
