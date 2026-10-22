<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Payment;
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

    /**
     * Retrieve authenticated student's balance and payment ledger newest-first.
     */
    public function payments(Request $request): JsonResponse
    {
        $user = $request->user() ?? $request->user('sanctum');
        $student = $user?->student;

        if (! $student) {
            return response()->json([
                'error' => 'UNAUTHORIZED_ROLE',
            ], 403);
        }

        $payments = $student->payments()
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();

        $items = $payments->map(fn (Payment $p): array => [
            'id' => $p->id,
            'or_number' => $p->or_number,
            'amount_centavos' => (int) $p->amount_centavos,
            'amount_formatted' => $p->amount_formatted,
            'payment_type' => $p->payment_type,
            'paid_at' => $p->paid_at->toIso8601String(),
            'receipt_url' => "/receipts/{$p->or_number}",
        ])->values();

        return response()->json([
            'student_id' => $student->id,
            'student_number' => $student->student_number,
            'balance_centavos' => max(0, (int) $student->balance_centavos),
            'balance_formatted' => $student->balance_formatted,
            'payments' => $items,
        ]);
    }
}
