<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\Controller;
use App\Models\Clearance;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentClearanceController extends Controller
{
    /**
     * Retrieve clearances scoped to caller's own department (FR-11.1).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user() ?? $request->user('sanctum');

        if (! $user instanceof User || ! $user->department_id) {
            return response()->json([
                'error' => 'UNAUTHORIZED_ROLE',
            ], 403);
        }

        $query = Clearance::query()
            ->with('student')
            ->where('department_id', $user->department_id);

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        /** @var Collection<int, Clearance> $clearances */
        $clearances = $query->get();

        $data = $clearances->map(fn (Clearance $c): array => [
            'id' => $c->id,
            'student_id' => $c->student_id,
            'student_number' => $c->student->student_number,
            'school_year' => $c->school_year,
            'semester' => (int) $c->semester,
            'status' => $c->status,
        ])->values();

        return response()->json($data);
    }

    /**
     * Review (approve/deny) a clearance within caller's own department (FR-11.2, FR-11.3).
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        $user = $request->user() ?? $request->user('sanctum');

        $clearance = Clearance::whereKey((int) $id)->first();
        if (! $clearance) {
            return response()->json([
                'error' => 'CLEARANCE_NOT_FOUND',
            ], 404);
        }

        if (! $user instanceof User || ! $user->department_id || (int) $clearance->department_id !== (int) $user->department_id) {
            return response()->json([
                'error' => 'UNAUTHORIZED_ROLE',
            ], 403);
        }

        $request->validate([
            'status' => ['required', 'string', 'in:approved,denied'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $clearance->status = (string) $request->input('status');
        $clearance->remarks = $request->input('remarks') !== null ? (string) $request->input('remarks') : null;
        $clearance->reviewed_by = $user->id;
        $clearance->reviewed_at = now();
        $clearance->save();

        return response()->json([
            'id' => $clearance->id,
            'status' => $clearance->status,
            'reviewed_at' => $clearance->reviewed_at->toISOString(),
        ], 200);
    }
}
