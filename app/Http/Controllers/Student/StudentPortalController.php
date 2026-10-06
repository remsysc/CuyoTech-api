<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentPortalController extends Controller
{
    /**
     * Retrieve authenticated student's profile.
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user() ?? $request->user('sanctum');
        $student = $user?->student;

        if (! $student) {
            return response()->json([
                'error' => 'UNAUTHORIZED_ROLE',
            ], 403);
        }

        return response()->json([
            'student_number' => $student->student_number,
            'name' => $user->name,
            'program' => $student->program,
            'year_level' => (int) $student->year_level,
            'status' => $student->status,
        ]);
    }

    /**
     * Retrieve enrolled subjects for the specified term.
     */
    public function subjects(Request $request): JsonResponse
    {
        $schoolYear = $request->query('school_year');
        $semester = $request->query('semester');

        if (! $request->filled('school_year') || ! $request->filled('semester')) {
            return response()->json([
                'error' => 'MISSING_TERM',
            ], 400);
        }

        $user = $request->user() ?? $request->user('sanctum');
        $student = $user?->student;

        if (! $student) {
            return response()->json([
                'error' => 'UNAUTHORIZED_ROLE',
            ], 403);
        }

        $enrollments = $student->enrollments()
            ->with('course')
            ->where('school_year', $schoolYear)
            ->where('semester', (int) $semester)
            ->get();

        $data = $enrollments->map(fn (Enrollment $enrollment) => [
            'course_code' => $enrollment->course->code,
            'title' => $enrollment->course->title,
            'units' => (int) $enrollment->course->units,
            'status' => $enrollment->status,
        ])->values();

        return response()->json($data);
    }

    /**
     * Retrieve completed grades for the specified term.
     */
    public function grades(Request $request): JsonResponse
    {
        $schoolYear = $request->query('school_year');
        $semester = $request->query('semester');

        if (! $request->filled('school_year') || ! $request->filled('semester')) {
            return response()->json([
                'error' => 'MISSING_TERM',
            ], 400);
        }

        $user = $request->user() ?? $request->user('sanctum');
        $student = $user?->student;

        if (! $student) {
            return response()->json([
                'error' => 'UNAUTHORIZED_ROLE',
            ], 403);
        }

        $enrollments = $student->enrollments()
            ->with('course')
            ->where('school_year', $schoolYear)
            ->where('semester', (int) $semester)
            ->where('status', 'completed')
            ->get();

        $data = $enrollments->map(fn (Enrollment $enrollment) => [
            'course_code' => $enrollment->course->code,
            'title' => $enrollment->course->title,
            'grade' => $enrollment->grade,
        ])->values();

        return response()->json($data);
    }
}
