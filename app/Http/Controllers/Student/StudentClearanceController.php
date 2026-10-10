<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Clearance;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentClearanceController extends Controller
{
    /**
     * Retrieve clearances across all departments for a student (FR-12).
     * Auth: role in (student [self only], department_staff, registrar, admin)
     */
    public function show(Request $request, int|string $id): JsonResponse
    {
        $user = $request->user() ?? $request->user('sanctum');

        if (! $user instanceof User) {
            return response()->json([
                'error' => 'UNAUTHENTICATED',
            ], 401);
        }

        $student = Student::whereKey((int) $id)->first();
        if (! $student) {
            return response()->json([
                'error' => 'STUDENT_NOT_FOUND',
            ], 404);
        }

        // Authorization check:
        // Accessible by department_staff, registrar, admin, and the student themselves (self-only)
        $allowedRoles = ['department_staff', 'registrar', 'admin'];

        if (! in_array($user->role, $allowedRoles, true)) {
            if ($user->role === 'student') {
                if ($user->id !== $student->user_id) {
                    return response()->json([
                        'error' => 'UNAUTHORIZED_ROLE',
                    ], 403);
                }
            } else {
                return response()->json([
                    'error' => 'UNAUTHORIZED_ROLE',
                ], 403);
            }
        }

        $clearances = Clearance::query()
            ->with('department')
            ->where('student_id', $student->id)
            ->get();

        $data = $clearances->map(fn (Clearance $c): array => [
            'department_code' => $c->department->code,
            'status' => $c->status,
            'remarks' => $c->remarks,
            'reviewed_at' => $c->reviewed_at?->toIso8601String(),
        ])->values();

        return response()->json($data);
    }
}
