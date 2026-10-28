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
     */
    public function index()
    {
        $this->authorize('viewAny', Department::class);

        return response()->json(
            Department::with([
                'positions' => function ($q) {
                    $q->orderBy('title');
                },
                'users' => function ($q) {
                    $q->select('id', 'name', 'email', 'role', 'job_title', 'department_id', 'status', 'card_uid')
                        ->orderBy('name');
                },
            ])
            ->withCount(['users', 'positions'])
            ->orderBy('name')
            ->get()
        );
    }

    /**
     * POST /api/departments
     */
    public function store(StoreDepartmentRequest $request)
    {
        $this->authorize('create', Department::class);

        $department = Department::create($request->validated());

        return response()->json($department->load('positions'), 201);
    }

    /**
     * PUT/PATCH /api/departments/{department}
     */
    public function update(UpdateDepartmentRequest $request, Department $department)
    {
        $this->authorize('update', $department);

        $department->update($request->validated());

        return response()->json($department->load('positions'));
    }

    /**
     * DELETE /api/departments/{department}
     */
    public function destroy(Department $department)
    {
        $this->authorize('delete', $department);

        $department->delete();

        return response()->json(null, 204);
    }
}
